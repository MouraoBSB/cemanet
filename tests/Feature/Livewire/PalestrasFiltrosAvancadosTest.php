<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasFiltrosAvancadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_ano(): void
    {
        Palestra::factory()->create(['titulo' => 'De 2024', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2024-05-10 19:00:00']);
        Palestra::factory()->create(['titulo' => 'De 2026', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-05-10 19:00:00']);

        Livewire::test(Lista::class)
            ->set('ano', '2026')
            ->assertSee('De 2026')
            ->assertDontSee('De 2024');
    }

    public function test_filtra_por_video_com(): void
    {
        Palestra::factory()->create(['titulo' => 'Com Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);
        Palestra::factory()->create(['titulo' => 'Sem Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        Livewire::test(Lista::class)
            ->set('video', 'com')
            ->assertSee('Com Video')
            ->assertDontSee('Sem Video');
    }

    public function test_filtra_por_video_sem(): void
    {
        Palestra::factory()->create(['titulo' => 'Com Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);
        Palestra::factory()->create(['titulo' => 'Sem Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        Livewire::test(Lista::class)
            ->set('video', 'sem')
            ->assertSee('Sem Video')
            ->assertDontSee('Com Video');
    }

    public function test_ordena_az(): void
    {
        $z = Palestra::factory()->create(['titulo' => 'Zelo e Fe', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2020-01-01 19:00:00']);
        $a = Palestra::factory()->create(['titulo' => 'Amor ao Proximo', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-01-01 19:00:00']);

        $ids = Livewire::test(Lista::class)
            ->set('ordenar', 'az')
            ->viewData('palestras')
            ->pluck('id')
            ->all();

        $this->assertSame([$a->id, $z->id], $ids);
    }

    public function test_anos_disponiveis_distintos_desc(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2024-05-10 19:00:00']);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-05-10 19:00:00']);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-08-10 19:00:00']);

        $anos = Livewire::test(Lista::class)->viewData('anos');

        $this->assertSame([2026, 2024], $anos->all());
    }

    public function test_pagina_traz_no_maximo_nove(): void
    {
        Palestra::factory()->count(11)->create(['status' => Palestra::STATUS_PUBLICADO]);

        $palestras = Livewire::test(Lista::class)->viewData('palestras');

        $this->assertCount(9, $palestras->items());
    }
}
