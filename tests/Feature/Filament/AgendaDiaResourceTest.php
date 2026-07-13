<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Resources\Agenda\Pages\CreateAgendaDia;
use App\Models\AgendaDia;
use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaDiaResourceTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);
    }

    public function test_cria_dia(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-01',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Reflexão do dia.</p>',
                'meta_dia_titulo' => 'Desenvolver Abnegação',
                'departamentos' => [$this->departamento->id],
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
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['data']);
    }

    public function test_salva_departamento(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-02',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $dia = AgendaDia::where('data', '2026-05-02')->first();
        $this->assertTrue($dia->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-03',
                'status' => AgendaDia::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
}
