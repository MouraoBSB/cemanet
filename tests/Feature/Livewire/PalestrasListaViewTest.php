<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasListaViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_barra_tem_ordenar_az_e_filtros_novos(): void
    {
        Livewire::test(Lista::class)
            ->assertSee('Título (A–Z)')
            ->assertSee('Com vídeo')
            ->assertSeeHtml('wire:model.live="ano"');
    }

    public function test_visao_grade_renderiza_card(): void
    {
        Palestra::factory()->create(['titulo' => 'Palestra Y', 'status' => Palestra::STATUS_PUBLICADO]);

        Livewire::test(Lista::class)->assertSee('Palestra Pública');
    }

    public function test_visao_lista_renderiza_linha(): void
    {
        Palestra::factory()->create(['titulo' => 'Palestra X', 'status' => Palestra::STATUS_PUBLICADO]);

        Livewire::test(Lista::class)
            ->set('visao', 'list')
            ->assertSee('Ver palestra')
            ->assertDontSee('Palestra Pública');
    }

    public function test_chip_e_limpar_tudo(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);

        Livewire::test(Lista::class)
            ->set('video', 'com')
            ->assertSee('Filtros ativos:')
            ->assertSee('Com vídeo')
            ->assertSee('Limpar tudo');
    }

    public function test_estado_vazio(): void
    {
        Livewire::test(Lista::class)
            ->set('q', 'inexistente-xyz')
            ->assertSee('Nenhuma palestra encontrada');
    }
}
