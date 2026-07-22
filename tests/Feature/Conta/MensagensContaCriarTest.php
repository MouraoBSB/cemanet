<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Livewire\Conta\MensagensConta;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Criação de Mensagem pelo médium em /minha-conta/mensagens (Fatia F4b, tasks 1-6 já entregam
 * migration + policy + serviços + schemaMedium). NUNCA usar actingAsAdmin(): o Gate::before do
 * admin passa em tudo e mascararia o eixo de AUTORIA (pertencimento), que é o que está sob teste.
 */
class MensagensContaCriarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function medium(): User
    {
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);

        return $user->fresh();
    }

    /** I3: nasce pendente, com a autoria correta e slug único (dois títulos iguais ⇒ slugs distintos). */
    public function test_criar_nasce_pendente_com_autoria_e_slug_gerado(): void
    {
        $medium = $this->medium();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Mensagem de Paz',
                'formato' => 'psicografia',
                'data_recebimento' => '2027-01-10',
                'corpo' => '<p>Corpo da mensagem.</p>',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Mensagem de Paz')->firstOrFail();
        $this->assertSame(Mensagem::STATUS_PENDENTE, $mensagem->status);
        $this->assertSame($medium->id, $mensagem->medium_id);
        $this->assertNull($mensagem->nivel);
        $this->assertNull($mensagem->publicado_por_id);
        $this->assertNull($mensagem->publicado_em);
        $this->assertNotEmpty($mensagem->slug);

        // Dois títulos iguais ⇒ slugs distintos.
        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Mensagem de Paz',
                'formato' => 'psicografia',
                'data_recebimento' => '2027-01-11',
                'corpo' => '<p>Outro corpo.</p>',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $slugs = Mensagem::where('titulo', 'Mensagem de Paz')->pluck('slug');
        $this->assertSame(2, $slugs->count());
        $this->assertSame(2, $slugs->unique()->count());
    }

    /** I4: direcionar=true + 2 destinatários ⇒ nivel='direcionada' + pivô com os dois ids. */
    public function test_direcionar_true_com_destinatarios_grava_nivel_e_pivo(): void
    {
        $medium = $this->medium();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Direcionada Dupla',
                'formato' => 'psicografia',
                'corpo' => '<p>x</p>',
                'direcionar' => true,
                'destinatarios' => [$u1->id, $u2->id],
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Direcionada Dupla')->firstOrFail();
        $this->assertSame(VisibilidadeMensagem::Direcionada->value, $mensagem->nivel);
        $this->assertSame(
            [$u1->id, $u2->id],
            $mensagem->destinatarios()->pluck('users.id')->sort()->values()->all()
        );
    }

    /** I4: direcionar=false ⇒ nivel null + 0 no pivô. */
    public function test_direcionar_false_nao_grava_nivel_nem_pivo(): void
    {
        $medium = $this->medium();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Não Direcionada',
                'formato' => 'psicografia',
                'corpo' => '<p>x</p>',
                'direcionar' => false,
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Não Direcionada')->firstOrFail();
        $this->assertNull($mensagem->nivel);
        $this->assertSame(0, $mensagem->destinatarios()->count());
    }

    /** I4: direcionar=true sem nenhum destinatário reprova o required condicional. */
    public function test_direcionar_true_sem_destinatario_reprova(): void
    {
        $medium = $this->medium();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Sem Destino',
                'formato' => 'psicografia',
                'corpo' => '<p>x</p>',
                'direcionar' => true,
                'destinatarios' => [],
            ])
            ->call('salvar')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertSame(0, Mensagem::where('titulo', 'Sem Destino')->count());
    }

    /**
     * I5a — VERMELHO do G1: criar com 1 autor espiritual ⇒ 1 linha em mensagem_autor_espiritual.
     * Sem `$this->form->model($mensagem)->saveRelationships()` este teste reprova com 0 linhas —
     * o Select `autores` usa ->relationship() (dehydrated(false)) e só grava nesse ponto.
     */
    public function test_i5a_autores_gravam_no_pivo(): void
    {
        $medium = $this->medium();
        $autor = AutorEspiritual::factory()->create();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm([
                'titulo' => 'Com Autor',
                'formato' => 'psicografia',
                'corpo' => '<p>x</p>',
                'autores' => [$autor->id],
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Com Autor')->firstOrFail();
        $this->assertSame(
            1,
            DB::table('mensagem_autor_espiritual')->where('mensagem_id', $mensagem->id)->count()
        );
    }

    /**
     * Trava do I5a: se alguém trocar ->relationship() por ->options(), o pivô passaria a gravar por
     * outro caminho (getState() comum), o teste acima ficaria verde SEM a linha do G1, e a
     * pictografia (que depende do mesmo dehydrated(false)) quebraria em produção sem cobertura.
     */
    public function test_campo_autores_e_multiplo_e_nao_dehydrated(): void
    {
        Livewire::actingAs($this->medium())->test(MensagensConta::class)
            ->call('novo')
            ->assertFormFieldExists('autores', fn (Select $f): bool => $f->isMultiple() && ! $f->isDehydrated());
    }

    /** Escopo da lista: só as próprias — vazamento real que faltava cobrir. */
    public function test_lista_mostra_so_as_proprias(): void
    {
        $medium = $this->medium();
        $outroMedium = $this->medium();

        Mensagem::factory()->pendente()->create(['titulo' => 'MINHA-PENDENTE', 'medium_id' => $medium->id]);
        Mensagem::factory()->pendente()->create(['titulo' => 'PENDENTE-DE-OUTRO-MEDIUM', 'medium_id' => $outroMedium->id]);

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->assertSee('MINHA-PENDENTE')
            ->assertDontSee('PENDENTE-DE-OUTRO-MEDIUM');
    }

    /** I7 (V5 — comportamento REAL): forjar destinatarios com direcionar=false não grava nada. */
    public function test_i7_forja_destinatarios_com_direcionar_false_nao_grava_pivo(): void
    {
        $medium = $this->medium();
        $u1 = User::factory()->create();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm(['titulo' => 'Forjada', 'formato' => 'psicografia', 'corpo' => '<p>x</p>'])
            ->set('data.direcionar', false)
            ->set('data.destinatarios', [$u1->id])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $mensagem = Mensagem::where('titulo', 'Forjada')->firstOrFail();
        $this->assertNull($mensagem->nivel);
        $this->assertSame(0, $mensagem->destinatarios()->count());
    }

    /**
     * I7: id inexistente injetado em destinatarios reprova a validação (o Select multiple injeta
     * Rule::in automática) — a prova do filtro server-side (integridade) é da Task 3.
     */
    public function test_i7_id_inexistente_reprova_validacao_e_nada_persiste(): void
    {
        $medium = $this->medium();

        Livewire::actingAs($medium)->test(MensagensConta::class)
            ->call('novo')
            ->fillForm(['titulo' => 'Id Inexistente', 'formato' => 'psicografia', 'corpo' => '<p>x</p>'])
            ->set('data.direcionar', true)
            ->set('data.destinatarios', [999999])
            ->call('salvar')
            ->assertHasErrors(['data.destinatarios.0']);

        $this->assertSame(0, Mensagem::count());
    }

    /** I22 (Task 5): o form do médium não tem os campos privilegiados/fora de escopo; tem os 9 do D6. */
    public function test_i22_campos_do_medium(): void
    {
        Livewire::actingAs($this->medium())->test(MensagensConta::class)
            ->call('novo')
            ->assertFormFieldDoesNotExist('nivel')
            ->assertFormFieldDoesNotExist('status')
            ->assertFormFieldDoesNotExist('slug')
            ->assertFormFieldDoesNotExist('link_arquivo')
            ->assertFormFieldDoesNotExist('liberar_download')
            ->assertFormFieldDoesNotExist('relacionadas')
            ->assertFormFieldDoesNotExist('resumo')   // I11: texto editorial da curadoria; o médium tem o `contexto`
            ->assertFormFieldExists('titulo')
            ->assertFormFieldExists('formato')
            ->assertFormFieldExists('data_recebimento')
            ->assertFormFieldExists('contexto')
            ->assertFormFieldExists('corpo')
            ->assertFormFieldExists('autores')
            ->assertFormFieldExists('imagens')
            ->assertFormFieldExists('direcionar')
            ->assertFormFieldExists('destinatarios');
    }
}
