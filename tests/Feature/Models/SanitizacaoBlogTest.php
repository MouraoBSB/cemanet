<?php

namespace Tests\Feature\Models;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizacaoBlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitiza_conteudo_remove_comentarios_e_classes(): void
    {
        $p = Post::factory()->create([
            'conteudo' => '<!-- wp:paragraph --><p class="jet-sm-gb-wrapper">Olá <script>alert(1)</script></p><!-- /wp:paragraph -->',
        ]);
        $this->assertStringNotContainsString('wp:paragraph', $p->conteudo);
        $this->assertStringNotContainsString('jet-sm-gb', $p->conteudo);
        $this->assertStringNotContainsString('<script>', $p->conteudo);
        $this->assertStringContainsString('Olá', $p->conteudo);
    }

    public function test_conteudo_nulo_permanece_nulo(): void
    {
        $p = Post::factory()->create(['conteudo' => null]);
        $this->assertNull($p->fresh()->conteudo);
    }

    public function test_conteudo_permite_iframe_youtube(): void
    {
        $iframe = '<iframe src="https://www.youtube.com/embed/abc123" width="560" height="315" allowfullscreen></iframe>';
        $p = Post::factory()->create(['conteudo' => $iframe]);
        $this->assertStringContainsString('youtube.com/embed', $p->conteudo);
    }

    public function test_conteudo_remove_iframe_externo_nao_permitido(): void
    {
        $iframe = '<iframe src="https://evil.com/hack"></iframe>';
        $p = Post::factory()->create(['conteudo' => $iframe]);
        $this->assertStringNotContainsString('evil.com', $p->conteudo);
    }
}
