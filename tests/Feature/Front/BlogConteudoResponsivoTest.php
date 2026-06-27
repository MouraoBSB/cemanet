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
     * O purifier deve ATIVAMENTE remover o style inline (largura em px) ao salvar,
     * preservando só as classes responsivas da allow-list. A fixture entra contaminada
     * com style="width:50px" para provar a remoção (não basta entrada já limpa).
     */
    public function test_purifier_remove_style_inline_e_px_preservando_classes(): void
    {
        $conteudo = <<<'HTML'
<figure class="wp-block-image size-large alignleft"><img src="/x.webp" alt="Foto" style="width:50px"></figure>
<p>Texto do artigo.</p>
HTML;

        $post = Post::factory()->create([
            'slug'     => 'post-sem-px',
            'status'   => Post::STATUS_PUBLICADO,
            'conteudo' => $conteudo,
        ]);

        // O style inline e a largura em px foram removidos...
        $this->assertStringNotContainsString('style=', $post->conteudo);
        $this->assertStringNotContainsString('50px', $post->conteudo);
        // ...mas as classes responsivas da allow-list sobreviveram.
        $this->assertStringContainsString('size-large', $post->conteudo);
        $this->assertStringContainsString('alignleft', $post->conteudo);
    }
}
