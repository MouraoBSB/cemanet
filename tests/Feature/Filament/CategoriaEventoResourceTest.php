<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Filament;

use App\Filament\Resources\CategoriasEvento\Pages\CreateCategoriaEvento;
use App\Models\CategoriaEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoriaEventoResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_categoria_com_cor(): void
    {
        Role::findOrCreate('administrador', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);

        Livewire::test(CreateCategoriaEvento::class)
            ->fillForm([
                'nome' => 'Vigília',
                'slug' => 'vigilia',
                'cor' => '#123456',
                'ordem' => 9,
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('#123456', CategoriaEvento::firstWhere('slug', 'vigilia')->cor);
    }
}
