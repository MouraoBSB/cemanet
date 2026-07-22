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
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Filament\Facades\Filament;
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

    private function pendente(array $attrs = []): Mensagem
    {
        return Mensagem::factory()->create([...['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null], ...$attrs]);
    }

    /** I18: a Action é a primeira escritora de publicado_em no painel. */
    public function test_action_publica_e_grava_autoria(): void
    {
        $m = $this->pendente(['slug' => 'a-publicar']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $f = $m->fresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $f->status);
        $this->assertNotNull($f->publicado_em);
        $this->assertSame(auth()->id(), $f->publicado_por_id);
    }

    /**
     * I17: contrato INVERSO ao do /minha-conta (CuradoriaConta:169 regenera). Aqui o slug é
     * campo de tela: regenerar sobrescreveria o que o admin digitou.
     */
    public function test_action_nao_altera_o_slug(): void
    {
        $m = $this->pendente(['slug' => 'slug-escolhido-a-mao', 'titulo' => 'Título Completamente Outro']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $this->assertSame('slug-escolhido-a-mao', $m->fresh()->slug);
    }

    /** I27: relacionadas não são fillable — fill() as descartaria em silêncio. */
    public function test_action_persiste_as_relacionadas_nos_dois_sentidos(): void
    {
        $b = Mensagem::factory()->create(['titulo' => 'Mensagem B']);
        $m = $this->pendente(['slug' => 'com-relacionada']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico', 'relacionadas' => [$b->id]])
            ->callAction('publicar');

        $this->assertTrue($m->fresh()->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $m->id), 'a relação não espelhou');
    }

    /**
     * I19: nível NULO pela UI. O slug inválido não é injetável por esta porta (o Select aplica
     * `Rule::in` sobre as 6 opções do enum) e já está coberto em
     * tests/Unit/Mensagens/RegraPublicacaoTest.php:27.
     */
    public function test_action_recusa_nivel_invalido(): void
    {
        $m = $this->pendente();

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => null])
            ->callAction('publicar')
            ->assertHasFormErrors(['nivel'], 'form');

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /** I19: o caminho que a validação NATIVA do Select não cobre. */
    public function test_action_recusa_direcionada_com_destinatario_inativo(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);
        $m = $this->pendente(['nivel' => VisibilidadeMensagem::Direcionada->value]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->callAction('publicar')
            ->assertHasFormErrors(['destinatarios'], 'form');

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /**
     * I20. SÓ assertActionHidden: visible(false) já protege no v5.6.7 — hidden ⇒ isDisabled()
     * ⇒ mountAction() desmonta e retorna null, e callAction() faz assertActionVisible() antes.
     * "Chamar numa publicada e afirmar que nada mudou" seria falso-verde.
     */
    public function test_action_nao_aparece_em_mensagem_ja_publicada(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertActionHidden('publicar');
    }

    /** I21: sem o refreshFormData, o próximo "Salvar alterações" despublica em silêncio. */
    public function test_depois_da_action_salvar_nao_despublica(): void
    {
        $m = $this->pendente(['slug' => 'nao-despublicar']);

        $tela = Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $tela->call('save')->assertHasNoFormErrors();

        $this->assertSame(Mensagem::STATUS_PUBLICADO, $m->fresh()->status);
    }

    /** I29. $portaForcada é ESTÁTICA e sobrevive entre testes do mesmo processo: resetar é obrigatório. */
    public function test_auditoria_da_action_registra_porta_admin(): void
    {
        AuditoriaAutorizacao::usarPorta(null);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $m = $this->pendente(['slug' => 'porta-admin']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $atividade = $m->fresh()->activities()->latest('id')->first();

        $this->assertSame('admin', $atividade->properties['porta']);
    }
}
