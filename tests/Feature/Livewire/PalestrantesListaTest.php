<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestrantes\Lista;
use App\Models\Palestra;
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

    public function test_busca_nunca_traz_inativo_mesmo_com_nome_correspondente(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Abadio Rodrigues']);
        Palestrante::factory()->inativo()->create(['nome' => 'Abadio Inativo']);

        Livewire::test(Lista::class)
            ->set('q', 'Abadio')
            ->assertSee('Abadio Rodrigues')
            ->assertDontSee('Abadio Inativo'); // o escopo ->ativo() barra inativos na busca
    }

    public function test_default_ordena_az_e_paginacao_12(): void
    {
        Palestrante::factory()->create(['nome' => 'Bruno']);
        Palestrante::factory()->create(['nome' => 'Ana']);
        Palestrante::factory()->create(['nome' => 'Carlos']);

        $pag = Livewire::test(Lista::class)->viewData('palestrantes');

        $this->assertSame(['Ana', 'Bruno', 'Carlos'], collect($pag->items())->pluck('nome')->all());
        $this->assertSame(12, $pag->perPage());
    }

    public function test_paginacao_limita_a_12_por_pagina(): void
    {
        Palestrante::factory()->count(13)->create();

        $pag = Livewire::test(Lista::class)->viewData('palestrantes');

        $this->assertSame(13, $pag->total());
        $this->assertCount(12, $pag->items());
    }

    public function test_ordenar_za_inverte(): void
    {
        Palestrante::factory()->create(['nome' => 'Ana']);
        Palestrante::factory()->create(['nome' => 'Bruno']);

        $pag = Livewire::test(Lista::class)->set('ordenar', 'za')->viewData('palestrantes');

        $this->assertSame(['Bruno', 'Ana'], collect($pag->items())->pluck('nome')->all());
    }

    public function test_ordenar_mais_e_menos_por_contagem_ignorando_diretor_e_rascunho(): void
    {
        $ana = Palestrante::factory()->create(['nome' => 'Ana']);   // 2 palestras publicadas como palestrante
        $bruno = Palestrante::factory()->create(['nome' => 'Bruno']); // 0 que contam

        $ana->palestras()->attach([
            Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id,
            Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id,
        ], ['papel' => Palestra::PAPEL_PALESTRANTE]);

        // Bruno: uma como DIRETOR (não conta) e uma RASCUNHO como palestrante (não conta) → count 0
        $bruno->palestras()->attach(Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id, ['papel' => Palestra::PAPEL_DIRETOR]);
        $bruno->palestras()->attach(Palestra::factory()->create(['status' => Palestra::STATUS_RASCUNHO])->id, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $mais = Livewire::test(Lista::class)->set('ordenar', 'mais')->viewData('palestrantes');
        $this->assertSame(['Ana', 'Bruno'], collect($mais->items())->pluck('nome')->all());
        $this->assertSame(0, collect($mais->items())->firstWhere('nome', 'Bruno')->palestras_ministradas_count);
        $this->assertSame(2, collect($mais->items())->firstWhere('nome', 'Ana')->palestras_ministradas_count);

        $menos = Livewire::test(Lista::class)->set('ordenar', 'menos')->viewData('palestrantes');
        $this->assertSame(['Bruno', 'Ana'], collect($menos->items())->pluck('nome')->all());
    }

    public function test_updated_reseta_pagina(): void
    {
        Palestrante::factory()->count(20)->create();

        // updated('q') → resetPage(): estava na pág. 2, volta para a 1.
        $c = Livewire::test(Lista::class)->call('gotoPage', 2)->set('q', 'z');

        $this->assertSame(1, $c->viewData('palestrantes')->currentPage());
    }

    public function test_limpar_filtros_zera_q_e_ordenar(): void
    {
        Livewire::test(Lista::class)
            ->set('q', 'algo')
            ->set('ordenar', 'za')
            ->call('limparFiltros')
            ->assertSet('q', '')
            ->assertSet('ordenar', 'az');
    }

    public function test_filtros_ativos_reflete_a_busca(): void
    {
        $c = Livewire::test(Lista::class);
        $this->assertSame([], $c->viewData('filtrosAtivos'));

        $c->set('q', 'ana');
        $ativos = $c->viewData('filtrosAtivos');
        $this->assertCount(1, $ativos);
        $this->assertSame('q', $ativos[0]['chave']);
    }
}
