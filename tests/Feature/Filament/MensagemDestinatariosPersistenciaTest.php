<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Models\Mensagem;
use App\Models\User;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosPersistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function direcionadaCom(array $ids, array $attrs = []): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create($attrs);
        $m->destinatarios()->sync($ids);

        return $m;
    }

    private function pivo(Mensagem $m): array
    {
        return DB::table('mensagem_destinatario')->where('mensagem_id', $m->id)
            ->pluck('user_id')->sort()->values()->all();
    }

    /** I2 (sucesso) — criar direcionada com ≥1 anexa o pivô certo. */
    public function test_cria_direcionada_anexa_destinatarios(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Recado', 'slug' => 'recado', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'direcionada',
                'destinatarios' => [$u1->id, $u2->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'recado')->firstOrFail();
        $this->assertSame([$u1->id, $u2->id], $this->pivo($m));
    }

    /** I3-fill — abrir o edit de uma direcionada pré-preenche o Select (prova o mutateFormDataBeforeFill). */
    public function test_edit_pre_preenche_destinatarios(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id, $u2->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormSet(['destinatarios' => [$u1->id, $u2->id]]);
    }

    /** I3-preservação — editar SÓ o título não apaga o pivô (sem o fill, o sync([]) apagaria). */
    public function test_edit_so_titulo_preserva_pivo(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id], ['titulo' => 'Antigo']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$u1->id], $this->pivo($m->fresh()));
    }

    /** I3-resync — trocar o conjunto reflete no pivô. */
    public function test_edit_re_sincroniza_conjunto(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id, $u2->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$u2->id, $u3->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$u2->id, $u3->id], $this->pivo($m->fresh()));
    }

    /** I2-edit — remover TODOS os destinatários de uma direcionada reprova e NÃO apaga o pivô. */
    public function test_edit_remover_todos_reprova_e_preserva(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => []])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertSame([$u1->id], $this->pivo($m->fresh())); // required barra o save → pivô intacto
    }

    /** I4-clear — trocar o nível para 'publico' esvazia o pivô (determinístico). */
    public function test_edit_troca_para_publico_esvazia_pivo(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->pivo($m->fresh()));
    }

    /** I6 — ponte F4a→3A→3C: o dado escrito pela página é exatamente o que o resolvedor 3A e a 3C leem. */
    public function test_ponte_para_resolvedor_e_3c(): void
    {
        $u = User::factory()->create();      // factory pura: nivelMaximo()=0, não-presidente (sem bypass veTudo)
        $outro = User::factory()->create();

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Ponte', 'slug' => 'ponte', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'direcionada',
                'destinatarios' => [$u->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'ponte')->firstOrFail();
        $this->assertTrue($m->podeSerVistoPor($u));
        $this->assertFalse($m->podeSerVistoPor($outro));
        $this->assertTrue(
            $u->mensagensDirecionadas()->publicado()
                ->where('nivel', VisibilidadeMensagem::Direcionada->value)->exists()
        );
    }

    /** I32: valida com efetivos() e gravava o conjunto CRU — o inativo entrava no pivô. */
    public function test_nao_grava_destinatario_inativo_no_pivo(): void
    {
        $ativo = User::factory()->create();
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$ativo->id, $inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$ativo->id, $inativo->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$ativo->id], $this->pivo($m));
    }

    /**
     * I32 (a metade que a UI alcança): id forjado é barrado pela `Rule::in` que o Select
     * multiple injeta em `data.destinatarios.*` (CanBeValidated:912-917 + Select:1741-1766) —
     * nunca chega a `sincronizar()`, logo NÃO há `QueryException` de FK a provar por esta
     * porta. O filtro de integridade de `aplicar()` é provado no nível de domínio, abaixo.
     */
    public function test_id_inexistente_reprova_na_validacao_e_nao_entra_no_pivo(): void
    {
        $u = User::factory()->create();
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$u->id, 999999]])
            ->call('save')
            // `.0` e NÃO `.1`, embora o id inválido seja o segundo: quando o estado tem id fora
            // das options, Select::getInValidationRuleValues() devolve [] e a regra vira `in:`
            // com lista VAZIA aplicada a `destinatarios.*` ⇒ TODOS os índices reprovam. Não
            // "corrigir" para .1 — isso quebra o teste.
            ->assertHasErrors(['data.destinatarios.0']);

        $this->assertSame([$u->id], $this->pivo($m));
        $this->assertSame(
            [$u->id],
            SincronizadorDestinatarios::efetivos(VisibilidadeMensagem::Direcionada->value, [$u->id, 999999]),
        );
    }
}
