<?php

// SPIKE (descartável): prova server-side dos critérios 2 e 3.

namespace Tests\Feature\Spike;

use App\Livewire\Spike\FormularioEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FormularioEventoSpikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_criterio_2_salva_com_validacao_do_filament_fora_do_painel(): void
    {
        $u = User::factory()->create();

        Livewire::actingAs($u)->test(FormularioEvento::class)
            ->set('data.titulo', 'Spike Evento')
            ->set('data.slug', 'spike-evento')
            ->set('data.data_inicio', '2026-08-01')
            ->set('data.status', Evento::STATUS_RASCUNHO)
            ->set('data.visibilidade', 'publico')
            ->call('salvar')
            ->assertHasNoErrors();

        $evento = Evento::firstWhere('slug', 'spike-evento');
        $this->assertNotNull($evento, 'o evento não foi criado');
        $this->assertSame('Spike Evento', $evento->titulo);
    }

    public function test_criterio_2_validacao_periodo_evento_roda_fora_do_painel(): void
    {
        $u = User::factory()->create();

        // hora_fim ANTES da hora_inicio no MESMO dia → regra de PeriodoEvento deve barrar.
        Livewire::actingAs($u)->test(FormularioEvento::class)
            ->set('data.titulo', 'Spike Inválido')
            ->set('data.slug', 'spike-invalido')
            ->set('data.data_inicio', '2026-08-01')
            ->set('data.hora_inicio', '10:00')
            ->set('data.data_fim', '2026-08-01')
            ->set('data.hora_fim', '09:00')
            ->set('data.status', Evento::STATUS_RASCUNHO)
            ->set('data.visibilidade', 'publico')
            ->call('salvar')
            ->assertHasErrors(['data.hora_fim']);

        $this->assertNull(Evento::firstWhere('slug', 'spike-invalido'), 'evento inválido NÃO deveria ter sido criado');
    }

    public function test_criterio_3_upload_do_spatie_persiste_fora_do_painel(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();

        Livewire::actingAs($u)->test(FormularioEvento::class)
            ->set('data.titulo', 'Spike Com Flyer')
            ->set('data.slug', 'spike-com-flyer')
            ->set('data.data_inicio', '2026-08-02')
            ->set('data.status', Evento::STATUS_RASCUNHO)
            ->set('data.visibilidade', 'publico')
            ->set('data.flyer', [UploadedFile::fake()->image('flyer.png', 600, 400)])
            ->call('salvar')
            ->assertHasNoErrors();

        $evento = Evento::firstWhere('slug', 'spike-com-flyer');
        $this->assertNotNull($evento);
        $this->assertTrue(
            $evento->getMedia(Evento::COLECAO_FLYER)->isNotEmpty(),
            'o flyer não foi persistido na Media Library fora do painel'
        );
    }
}
