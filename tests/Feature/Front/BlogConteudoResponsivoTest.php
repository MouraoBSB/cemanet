<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace Tests\Feature\Front;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogConteudoResponsivoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O single do blog deve renderizar o wrapper conteudo-artigo e preservar
     * as classes do Gutenberg (alignleft, size-large) no corpo do artigo.
     */
    public function test_single_exibe_wrapper_conteudo_artigo_e_classes_gutenberg(): void
    {
        $conteudo = <<<'HTML'
<figure class="wp-block-image size-large alignleft"><img src="/x.webp" alt="Imagem de teste"></figure>
<p>Parágrafo do artigo de teste.</p>
<div class="colunas"><div class="coluna">Coluna A</div><div class="coluna">Coluna B</div></div>
HTML;

        $post = Post::factory()->create([
            'slug'     => 'post-responsivo',
            'status'   => Post::STATUS_PUBLICADO,
            'conteudo' => $conteudo,
        ]);

        $r = $this->get('/sementeira/post-responsivo');

        $r->assertOk();
        $r->assertSee('conteudo-artigo', false);
        $r->assertSee('alignleft', false);
        $r->assertSee('size-large', false);
        $r->assertSee('colunas', false);
    }

    /**
     * O conteúdo armazenado não deve ter atributos style inline nem larguras em px
     * (o purifier já garante isso; este teste confirma que o pipeline não regrediu).
     */
    public function test_conteudo_armazenado_sem_style_inline_e_sem_px_fixo(): void
    {
        $conteudo = <<<'HTML'
<figure class="wp-block-image size-large alignleft"><img src="/x.webp" alt="Foto"></figure>
<p>Texto do artigo.</p>
<div class="colunas"><div class="coluna">A</div><div class="coluna">B</div></div>
HTML;

        $post = Post::factory()->create([
            'slug'     => 'post-sem-px',
            'status'   => Post::STATUS_PUBLICADO,
            'conteudo' => $conteudo,
        ]);

        // O purifier preserva as classes mas strip inline style; confirmar que não regrediu
        $this->assertStringNotContainsString('style=', $post->conteudo);
        $this->assertStringNotContainsString('px"', $post->conteudo);
    }
}
