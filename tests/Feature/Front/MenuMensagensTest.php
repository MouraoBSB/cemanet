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

        // "Mensagens Públicas" só existe no rótulo do submenu do header (ativo => true
        // em config/navegacao.php); não aparece em nenhum outro trecho da página
        // (o corpo usa "Autores Espirituais"/"Palestras Públicas"/etc. no bloco "Veja
        // também", o H1 usa "Mensagens Mediúnicas"). Isso garante que o teste falhe de
        // verdade se o item do menu for desativado, ao contrário de checar só a rota.
        $res = $this->get(route('mensagens.index'));
        $res->assertSee('Mensagens Públicas');
    }
}
