<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclui_publica_e_exclui_nao_publica(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub-msg']);
        Mensagem::factory()->pendente()->create(['slug' => 'pend-msg']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'rest-msg']);

        $res = $this->get('/sitemap.xml');
        $res->assertOk()->assertHeader('Content-Type', 'application/xml');
        $res->assertSee(route('mensagens.index'), false);
        $res->assertSee('/mensagens-mediunicas/pub-msg', false);
        $res->assertDontSee('/mensagens-mediunicas/pend-msg', false);
        $res->assertDontSee('/mensagens-mediunicas/rest-msg', false);
    }
}
