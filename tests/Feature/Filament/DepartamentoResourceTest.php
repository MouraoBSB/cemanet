<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Filament;

use App\Filament\Resources\Departamentos\Pages\CreateDepartamento;
use App\Filament\Resources\Departamentos\Pages\ListDepartamentos;
use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepartamentoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_listagem_de_departamentos_renderiza(): void
    {
        Livewire::test(ListDepartamentos::class)
            ->assertSuccessful();
    }

    public function test_cria_departamento(): void
    {
        Livewire::test(CreateDepartamento::class)
            ->fillForm([
                'sigla' => 'DEV',
                'nome' => 'Desenvolvimento',
                'slug' => 'desenvolvimento',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Departamento::class, [
            'sigla' => 'DEV',
            'nome' => 'Desenvolvimento',
            'slug' => 'desenvolvimento',
        ]);
    }
}
