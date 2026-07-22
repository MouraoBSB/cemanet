<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Schemas\MensagemForm;   // MSG_NIVEL_OBRIGATORIO — sem isto, erro FATAL
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemPublicarActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /** I22: o Select publicava sem passar por regra nenhuma. */
    public function test_salvar_publicado_sem_nivel_e_recusado(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null])
            ->call('save')
            ->assertHasFormErrors(['nivel' => MensagemForm::MSG_NIVEL_OBRIGATORIO]);

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /** I23: mesmo buraco na criação — o status nasce publicado por default. */
    public function test_criar_publicado_sem_nivel_e_recusado(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Sem nível', 'slug' => 'sem-nivel', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['nivel']);

        $this->assertSame(0, Mensagem::where('slug', 'sem-nivel')->count());
    }

    /** I24: publicar pelo Select grava autoria, igual à Action. */
    public function test_publicar_pelo_select_grava_autoria(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico'])
            ->call('save')
            ->assertHasNoFormErrors();

        $f = $m->fresh();
        $this->assertNotNull($f->publicado_em);
        $this->assertSame(auth()->id(), $f->publicado_por_id);
    }

    /**
     * I26 — o BLOQUEADOR: as 133 publicadas do acervo têm publicado_em NULL. Um gatilho por
     * ESTADO ("publicado e publicado_em null") gravaria "publicada hoje, por mim" em qualquer
     * edição de qualquer uma delas. O gatilho é a TRANSIÇÃO.
     */
    public function test_editar_titulo_de_publicada_nao_carimba_autoria(): void
    {
        $m = Mensagem::factory()->create([
            'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico',
            'publicado_em' => null, 'publicado_por_id' => null, 'titulo' => 'Antigo',
        ]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $f = $m->fresh();
        $this->assertSame('Novo', $f->titulo);
        $this->assertNull($f->publicado_em, 'autoria falsa carimbada numa publicada antiga');
        $this->assertNull($f->publicado_por_id);
    }

    /**
     * I30: sem `$hasDatabaseTransactions` o rollback do Filament é no-op e o save fica pela
     * metade. ⚠️ O cenário TEM de recusar pelo SERVIDOR, não pela validação nativa: com
     * `nivel = null` o `required` da Task 11 barra ANTES de `getState()` rodar
     * `saveRelationships()`, e o teste passaria por vacuidade sem provar transação nenhuma.
     * Por isso a recusa aqui vem do destinatário INATIVO — nível válido, validação nativa
     * satisfeita, e a reasserção lançando depois de autores e mídia já terem sido gravados.
     */
    public function test_save_recusado_nao_deixa_autores_gravados(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)
            ->create(['status' => Mensagem::STATUS_PENDENTE]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm([
                'status' => Mensagem::STATUS_PUBLICADO,
                'destinatarios' => [$inativo->id],
                'autores' => [$autor->id],
            ])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertCount(0, $m->fresh()->autores, 'meio-save: autores gravados apesar da recusa');
    }

    /** I24, a outra metade: nascer publicada também carimba — prova que o hook do Create é o afterCreate. */
    public function test_criar_ja_publicada_grava_autoria(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Nasce publicada', 'slug' => 'nasce-publicada', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'nasce-publicada')->firstOrFail();
        $this->assertNotNull($m->publicado_em);
        $this->assertSame(auth()->id(), $m->publicado_por_id);
    }

    /** I19/I22: direcionada cujo único destinatário está inativo não vai ao ar invisível. */
    public function test_publicar_direcionada_com_destinatario_inativo_e_recusado(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)
            ->create(['status' => Mensagem::STATUS_PENDENTE]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'destinatarios' => [$inativo->id]])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);
    }
}
