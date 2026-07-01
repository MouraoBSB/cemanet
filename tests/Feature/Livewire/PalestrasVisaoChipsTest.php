<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasVisaoChipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visao_padrao_e_grid(): void
    {
        Livewire::test(Lista::class)->assertSet('visao', 'grid');
    }

    public function test_alternar_visao_para_lista(): void
    {
        Livewire::test(Lista::class)
            ->call('alternarVisao', 'list')
            ->assertSet('visao', 'list');
    }

    public function test_trocar_visao_nao_reseta_pagina(): void
    {
        Palestra::factory()->count(11)->create(['status' => Palestra::STATUS_PUBLICADO]);

        $c = Livewire::test(Lista::class)->call('gotoPage', 2);
        $this->assertSame(2, $c->viewData('palestras')->currentPage());

        $c->call('alternarVisao', 'list');
        $this->assertSame(2, $c->viewData('palestras')->currentPage());
    }

    public function test_filtro_gera_chip_e_remover_limpa(): void
    {
        $c = Livewire::test(Lista::class)->set('video', 'com');

        $chips = collect($c->instance()->filtrosAtivos());
        $this->assertTrue($chips->contains(fn ($chip) => $chip['chave'] === 'video'));

        $c->call('removerFiltro', 'video')->assertSet('video', '');
    }

    public function test_limpar_filtros_mantem_visao(): void
    {
        Livewire::test(Lista::class)
            ->set('visao', 'list')
            ->set('assunto', 'mediunidade')
            ->call('limparFiltros')
            ->assertSet('assunto', '')
            ->assertSet('visao', 'list');
    }
}
