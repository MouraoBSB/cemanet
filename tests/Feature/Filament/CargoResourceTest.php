<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Filament;

use App\Filament\Resources\Cargos\Pages\CreateCargo;
use App\Filament\Resources\Cargos\Pages\EditCargo;
use App\Filament\Resources\Cargos\Pages\ListCargos;
use App\Models\Cargo;
use App\Models\Departamento;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->actingAsAdmin();
    }

    public function test_listagem_de_cargos_renderiza(): void
    {
        Livewire::test(ListCargos::class)
            ->assertSuccessful();
    }

    public function test_cria_cargo(): void
    {
        $departamento = Departamento::first();

        Livewire::test(CreateCargo::class)
            ->fillForm([
                'nome' => 'Coordenador de Teste',
                'slug' => 'coordenador-de-teste',
                'institucional' => false,
                'departamento_id' => $departamento->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Cargo::class, [
            'nome' => 'Coordenador de Teste',
            'slug' => 'coordenador-de-teste',
            'departamento_id' => $departamento->id,
        ]);
    }

    public function test_editar_cargo_marcando_institucional_limpa_departamento(): void
    {
        $departamento = Departamento::first();

        $cargo = Cargo::create([
            'nome' => 'Cargo com Departamento',
            'slug' => 'cargo-com-departamento',
            'departamento_id' => $departamento->id,
            'institucional' => false,
        ]);

        Livewire::test(EditCargo::class, ['record' => $cargo->getKey()])
            ->fillForm([
                'institucional' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Cargo::class, [
            'id' => $cargo->id,
            'institucional' => true,
            'departamento_id' => null,
        ]);
    }
}
