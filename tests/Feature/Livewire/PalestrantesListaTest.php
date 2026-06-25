<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestrantes\Lista;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrantesListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_filtra_por_nome(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Abadio Rodrigues']);
        Palestrante::factory()->ativo()->create(['nome' => 'Bezerra de Menezes']);

        Livewire::test(Lista::class)
            ->set('q', 'Abadio')
            ->assertSee('Abadio Rodrigues')
            ->assertDontSee('Bezerra de Menezes');
    }
}
