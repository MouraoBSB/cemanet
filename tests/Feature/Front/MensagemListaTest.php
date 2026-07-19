<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_so_lista_publicas(): void
    {
        $pub = Mensagem::factory()->publica()->create(['titulo' => 'Mensagem Pública']);
        Mensagem::factory()->pendente()->create(['titulo' => 'Mensagem Pendente']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Mensagem Restrita']);

        Livewire::test(Lista::class)
            ->assertSee('Mensagem Pública')
            ->assertDontSee('Mensagem Pendente')
            ->assertDontSee('Mensagem Restrita');
    }

    public function test_filtra_por_autor_por_slug(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Bezerra de Menezes', 'slug' => 'bezerra-de-menezes']);
        $doAutor = Mensagem::factory()->publica()->create(['titulo' => 'Do Autor']);
        $doAutor->autores()->sync([$autor->id]);
        Mensagem::factory()->publica()->create(['titulo' => 'Sem Autor']);

        Livewire::test(Lista::class)
            ->set('autor', 'bezerra-de-menezes')
            ->assertSee('Do Autor')
            ->assertDontSee('Sem Autor');
    }

    public function test_filtra_sem_assinatura(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $comAutor = Mensagem::factory()->publica()->create(['titulo' => 'Com Autor']);
        $comAutor->autores()->sync([$autor->id]);
        Mensagem::factory()->publica()->create(['titulo' => 'Anônima']);

        Livewire::test(Lista::class)
            ->set('autor', 'sem-assinatura')
            ->assertSee('Anônima')
            ->assertDontSee('Com Autor');
    }

    public function test_filtra_por_periodo(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'Antiga', 'data_recebimento' => '2020-01-01']);
        Mensagem::factory()->publica()->create(['titulo' => 'Recente', 'data_recebimento' => '2025-01-01']);

        Livewire::test(Lista::class)
            ->set('dataDe', '2024-01-01')
            ->assertSee('Recente')
            ->assertDontSee('Antiga');
    }

    public function test_alternar_visao_nao_reseta_pagina(): void
    {
        Mensagem::factory()->publica()->count(12)->create();

        // R2: se viewData()->currentPage() se comportar diferente no Livewire 4, cair para o padrão
        // da casa (PalestrasListaTest usa ->html()/assertSee do conteúdo da página 2).
        $c = Livewire::test(Lista::class)->call('setPage', 2);
        $c->call('alternarVisao', 'list')->assertSet('visao', 'list');
        $this->assertSame(2, $c->viewData('mensagens')->currentPage());
    }

    public function test_estado_vazio_quando_sem_publicas(): void
    {
        Mensagem::factory()->pendente()->create();

        Livewire::test(Lista::class)->assertSee('Nenhuma mensagem'); // texto do estado vazio (SPEC §4.1)
    }
}
