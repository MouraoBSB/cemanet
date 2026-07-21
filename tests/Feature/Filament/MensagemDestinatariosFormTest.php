<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Models\Mensagem;
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
     * Cobre 'publico' e 'trabalhadores' (§9.2); o caso nivel=null já é coberto pela regressão
     * do MensagemResourceTest (os creates existentes nascem com nivel default null).
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
}
