<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Resources\Agenda\Pages\CreateAgendaDia;
use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaDiaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_cria_dia(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-01',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Reflexão do dia.</p>',
                'meta_dia_titulo' => 'Desenvolver Abnegação',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('agenda_dias', [
            'data' => '2026-05-01',
            'status' => AgendaDia::STATUS_PUBLICADO,
            'meta_dia_titulo' => 'Desenvolver Abnegação',
        ]);
    }

    public function test_rejeita_data_duplicada(): void
    {
        AgendaDia::factory()->create(['data' => '2026-05-01']);

        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-01',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Outra reflexão para a mesma data.</p>',
            ])
            ->call('create')
            ->assertHasFormErrors(['data']);
    }
}
