<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_filtra_por_titulo(): void
    {
        Palestra::factory()->create(['titulo' => 'Paz e Amor']);
        Palestra::factory()->create(['titulo' => 'Caridade Silenciosa']);

        Livewire::test(Lista::class)
            ->set('q', 'Paz')
            ->assertSee('Paz e Amor')
            ->assertDontSee('Caridade Silenciosa');
    }

    public function test_ordena_por_data_desc(): void
    {
        $antiga = Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00']);
        $nova = Palestra::factory()->create(['titulo' => 'Nova', 'data_da_palestra' => '2026-01-01 16:00:00']);

        $html = Livewire::test(Lista::class)->html();

        $this->assertLessThan(strpos($html, 'Antiga'), strpos($html, 'Nova'));
    }
}
