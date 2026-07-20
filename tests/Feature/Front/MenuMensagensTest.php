<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuMensagensTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_menu_mensagens_ativo_com_submenu_autores(): void
    {
        $item = collect(config('navegacao.menu'))->firstWhere('rotulo', 'Mensagens Mediúnicas');

        $this->assertTrue($item['ativo']);
        $this->assertSame('mensagens.index', $item['rota']);
        $this->assertContains('autores.index', array_column($item['itens'], 'rota'));
    }

    public function test_header_mostra_links_mensagens_e_autores(): void
    {
        Mensagem::factory()->publica()->create(); // garante a página renderizar o layout/header

        $res = $this->get(route('mensagens.index'));
        $res->assertSee(route('mensagens.index'), false);
        $res->assertSee(route('autores.index'), false);
    }
}
