<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_nivel_e_live_e_destinatarios_e_multiplo(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => $f->isLive())
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => $f->isMultiple());
    }

    public function test_destinatarios_visivel_so_quando_direcionada(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldVisible('destinatarios')
            ->fillForm(['nivel' => 'publico'])
            ->assertFormFieldHidden('destinatarios')
            ->fillForm(['nivel' => null])
            ->assertFormFieldHidden('destinatarios');
    }

    /** VERMELHO #1 (I2): salvar direcionada SEM destinatário reprova o required condicional (não persiste). */
    public function test_direcionada_sem_destinatario_reprova(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'A direcionar',
                'slug' => 'a-direcionar',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
                'nivel' => 'direcionada',
                'destinatarios' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertDatabaseMissing('mensagens', ['slug' => 'a-direcionar']);
    }

    /**
     * I5: qualquer nível NÃO-direcionado sem destinatário salva (required é condicional).
     * Cobre 'publico' e 'trabalhadores' (§9.2); o caso nivel=null NÃO é coberto aqui — a Task 11
     * tornou `nivel` required quando `status = publicado`, então null só é aceito com
     * `status = pendente` (I25, ver MensagemResourceTest::test_rascunho_pendente_sem_nivel_salva);
     * `publicado` + `nivel = null` é recusado (MensagemPublicarActionTest::test_salvar_publicado_sem_nivel_e_recusado).
     */
    public function test_nao_direcionada_sem_destinatario_salva(): void
    {
        foreach (['publico', 'trabalhadores'] as $nivel) {
            Livewire::test(CreateMensagem::class)
                ->fillForm([
                    'titulo' => "Sem destino {$nivel}",
                    'slug' => "sem-destino-{$nivel}",
                    'formato' => 'psicografia',
                    'status' => Mensagem::STATUS_PUBLICADO,
                    'nivel' => $nivel,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('mensagens', ['slug' => "sem-destino-{$nivel}", 'nivel' => $nivel]);
        }
    }

    /** I7: a busca é server-side sobre `name` (não sobre o HTML) e só oferece ATIVOS. */
    public function test_busca_de_destinatarios_e_por_nome_e_so_ativos(): void
    {
        $ativo = User::factory()->create(['name' => 'Ana Ativa']);
        User::factory()->create(['name' => 'Ivo Inativo', 'ativo' => false]);

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use ($ativo): bool {
                $porNome = $f->getSearchResults('Ana');           // acha o ativo
                $inativo = $f->getSearchResults('Ivo');           // NÃO acha o inativo (where ativo=true)
                $porHtml = $f->getSearchResults('span');          // termo que só existe no markup do avatar

                return array_key_exists($ativo->id, $porNome)
                    && $inativo === []
                    && $porHtml === [];
            });
    }

    /**
     * I8(a): quem JÁ está selecionado é hidratado mesmo tendo sido desativado depois —
     * getOptionLabelsUsing (whereKey SEM filtro ativo) faz o papel do antigo orWhereIn; senão
     * getInValidationRuleValues devolve [] e o Rule::in trava até um simples Salvar de título.
     */
    public function test_selecionado_que_ficou_inativo_e_hidratado(): void
    {
        $u = User::factory()->create(['name' => 'Ivo Desativado Depois']);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);
        $u->update(['ativo' => false]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getOptionLabels()));
    }

    /**
     * I9: busca e hidratação eager-loadam perfil.media — a busca dispara EXATAMENTE 1 query na
     * tabela `media` (o whereIn eager), não 1 por usuário (N+1). R1: conta só o que toca `media`.
     */
    public function test_busca_de_destinatarios_carrega_a_midia_em_uma_query(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            User::factory()->create(['name' => "Teste {$i}"])->perfil()->create([]); // perfil sem foto: exercita os 2 hops (perfil + media)
        }

        $queriesDeMidia = 0;
        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use (&$queriesDeMidia): bool {
                DB::connection()->flushQueryLog();
                DB::connection()->enableQueryLog();
                $f->getSearchResults('Teste');
                $queriesDeMidia = collect(DB::connection()->getQueryLog())
                    ->filter(fn (array $q): bool => str_contains($q['query'], '"media"'))
                    ->count();
                DB::connection()->disableQueryLog();

                return true;
            });

        $this->assertSame(1, $queriesDeMidia, 'a busca deve eager-loadar perfil.media numa única query (sem N+1)');
    }

    /** I10: usuário SEM PerfilMembro passa pelas closures (?->) sem "read property on null". */
    public function test_usuario_sem_perfil_nao_quebra(): void
    {
        $u = User::factory()->create(['name' => 'Sem Perfil']); // sem $u->perfil()->create()

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getSearchResults('Sem Perfil')));
    }
}
