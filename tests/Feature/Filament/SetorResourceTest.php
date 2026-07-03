<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Filament;

use App\Filament\Resources\Setors\Pages\CreateSetor;
use App\Filament\Resources\Setors\Pages\ListSetors;
use App\Models\Setor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SetorResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_listagem_de_setores_renderiza(): void
    {
        Livewire::test(ListSetors::class)
            ->assertSuccessful();
    }

    public function test_cria_setor(): void
    {
        Livewire::test(CreateSetor::class)
            ->fillForm([
                'nome' => 'Recepção',
                'slug' => 'recepcao',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Setor::class, [
            'nome' => 'Recepção',
            'slug' => 'recepcao',
        ]);
    }
}
