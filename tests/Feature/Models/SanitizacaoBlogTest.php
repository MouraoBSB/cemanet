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
        $this->assertStringContainsString('allowfullscreen', $p->conteudo);
    }

    public function test_conteudo_remove_iframe_externo_nao_permitido(): void
    {
        $iframe = '<iframe src="https://evil.com/hack"></iframe>';
        $p = Post::factory()->create(['conteudo' => $iframe]);
        $this->assertStringNotContainsString('evil.com', $p->conteudo);
    }

    /** data-id sobrevive; classes da allow-list sobrevivem; classe proibida e style somem */
    public function test_sanitiza_mantem_data_id_e_classes_de_imagem(): void
    {
        $html = '<figure class="wp-block-image size-large alignleft x">'
            . '<img src="/s/1/a.webp" data-id="42" alt="" class="alignright evil" style="width:9px"></figure>';

        $p = Post::factory()->create(['conteudo' => $html]);

        $this->assertStringContainsString('data-id="42"', $p->conteudo);

        foreach (['wp-block-image', 'size-large', 'alignleft', 'alignright'] as $classe) {
            $this->assertStringContainsString($classe, $p->conteudo);
        }

        $this->assertStringNotContainsString('evil', $p->conteudo);
        $this->assertStringNotContainsString('style=', $p->conteudo);
    }

    public function test_conteudo_preserva_alinhamento_de_texto_por_classe(): void
    {
        $post = Post::factory()->make([
            'conteudo' => '<p class="has-text-align-justify">Justificado.</p>'
                . '<h2 class="has-text-align-center">Centro</h2>'
                . '<p class="has-text-align-right">Direita</p>',
        ]);

        $html = $post->conteudo;

        $this->assertStringContainsString('has-text-align-justify', $html);
        $this->assertStringContainsString('has-text-align-center', $html);
        $this->assertStringContainsString('has-text-align-right', $html);
    }

    public function test_conteudo_remove_classe_de_paragrafo_fora_da_allowlist(): void
    {
        $post = Post::factory()->make([
            'conteudo' => '<p class="classe-maliciosa has-text-align-left">x</p>',
        ]);

        $html = $post->conteudo;

        $this->assertStringNotContainsString('classe-maliciosa', $html);
        $this->assertStringContainsString('has-text-align-left', $html);
    }

    /** perfil 'conteudo' (palestras) deve continuar sem data-id nem classes de imagem */
    public function test_perfil_conteudo_palestras_nao_permite_data_id_nem_classes(): void
    {
        $html = '<figure class="wp-block-image alignleft">'
            . '<img src="/s/1/a.webp" data-id="42" alt="" class="alignright"></figure>';

        $limpo = clean($html, 'conteudo');

        $this->assertStringNotContainsString('data-id', $limpo);
        $this->assertStringNotContainsString('alignright', $limpo);
        $this->assertStringNotContainsString('alignleft', $limpo);
        $this->assertStringNotContainsString('wp-block-image', $limpo);
    }
}
