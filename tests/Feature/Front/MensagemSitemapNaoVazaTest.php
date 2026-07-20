<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSitemapNaoVazaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_so_publico_apos_3b(): void
    {
        $pub = Mensagem::factory()->publica()->create(['slug' => 'pub-sm']);
        $rest = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'rest-sm']);

        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertSee(route('mensagens.show', 'pub-sm'), false);   // pública indexada
        $res->assertDontSee(route('mensagens.show', 'rest-sm'), false); // restrita fora (I12)
    }
}
