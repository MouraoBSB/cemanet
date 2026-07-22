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

    /** I31: o Select não pode oferecer quem a regra vai descartar. */
    public function test_select_de_destinatarios_nao_oferece_usuario_inativo(): void
    {
        $ativo = User::factory()->create(['name' => 'Ana Ativa']);
        $inativo = User::factory()->create(['name' => 'Ivo Inativo', 'ativo' => false]);

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use ($ativo, $inativo): bool {
                $opcoes = $f->getOptions();

                return array_key_exists($ativo->id, $opcoes) && ! array_key_exists($inativo->id, $opcoes);
            });
    }

    /**
     * I31 (a outra metade): quem JÁ está selecionado continua na lista mesmo tendo sido
     * desativado depois — senão o Select injeta Rule::in(options) sem o id hidratado pelo
     * fill() e trava até um simples Salvar de título, sem a opção aparecer para ser removida.
     */
    public function test_select_mantem_o_destinatario_ja_selecionado_que_ficou_inativo(): void
    {
        $u = User::factory()->create(['name' => 'Ivo Desativado Depois']);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);
        $u->update(['ativo' => false]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getOptions()));
    }
}
