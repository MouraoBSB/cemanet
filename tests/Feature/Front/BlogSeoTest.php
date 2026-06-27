<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Front;

use App\Models\Categoria;
use App\Models\Post;
use App\Models\PostFaq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogSeoTest extends TestCase
{
    use RefreshDatabase;

    private function postPublicado(array $attrs = []): Post
    {
        return Post::factory()->create(array_merge([
            'titulo'          => 'A Luz da Caridade',
            'slug'            => 'a-luz-da-caridade',
            'resumo'          => 'Um artigo sobre caridade espírita.',
            'status'          => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDay(),
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // JSON-LD Article
    // -----------------------------------------------------------------------

    public function test_single_tem_jsonld_article(): void
    {
        $this->postPublicado();

        $resp = $this->get(route('blog.show', 'a-luz-da-caridade'));

        $resp->assertOk();
        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Article"', false);
    }

    public function test_jsonld_contem_organization_cema(): void
    {
        $this->postPublicado();

        $resp = $this->get(route('blog.show', 'a-luz-da-caridade'));

        $resp->assertSee('Organization', false);
        $resp->assertSee('Centro Espírita Maria Madalena', false);
    }

    public function test_jsonld_omite_image_quando_post_sem_imagem_destacada(): void
    {
        // Sem mídia anexada, "image":null é inválido para Article (schema.org) — deve ser omitido.
        Storage::fake('public');
        $this->postPublicado(['slug' => 'post-sem-imagem']);

        $resp = $this->get(route('blog.show', 'post-sem-imagem'));

        $resp->assertOk();
        $resp->assertDontSee('"image":null', false);
    }

    // -----------------------------------------------------------------------
    // FAQPage condicional
    // -----------------------------------------------------------------------

    public function test_jsonld_tem_faqpage_quando_post_tem_faqs(): void
    {
        $post = $this->postPublicado(['slug' => 'post-com-faq']);
        $post->faqs()->create(['pergunta' => 'O que é caridade?', 'resposta' => 'É o amor em ação.', 'ordem' => 0]);

        $resp = $this->get(route('blog.show', 'post-com-faq'));

        $resp->assertSee('"@type":"FAQPage"', false);
        $resp->assertSee('O que é caridade?', false);
    }

    public function test_jsonld_nao_tem_faqpage_quando_post_sem_faqs(): void
    {
        $this->postPublicado(['slug' => 'post-sem-faq']);

        $resp = $this->get(route('blog.show', 'post-sem-faq'));

        $resp->assertDontSee('"@type":"FAQPage"', false);
    }

    // -----------------------------------------------------------------------
    // robots_noindex
    // -----------------------------------------------------------------------

    public function test_robots_noindex_injeta_meta_noindex(): void
    {
        $this->postPublicado(['slug' => 'post-noindex', 'robots_noindex' => true]);

        $resp = $this->get(route('blog.show', 'post-noindex'));

        $resp->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_post_indexavel_nao_tem_meta_noindex(): void
    {
        $this->postPublicado(['slug' => 'post-indexavel', 'robots_noindex' => false]);

        $resp = $this->get(route('blog.show', 'post-indexavel'));

        $resp->assertDontSee('<meta name="robots" content="noindex">', false);
    }

    // -----------------------------------------------------------------------
    // Canonical
    // -----------------------------------------------------------------------

    public function test_canonical_presente(): void
    {
        $this->postPublicado();

        $resp = $this->get(route('blog.show', 'a-luz-da-caridade'));

        $resp->assertSee('rel="canonical"', false);
    }

    public function test_canonical_usa_campo_canonical_quando_definido(): void
    {
        $this->postPublicado([
            'canonical' => 'https://externo.exemplo.com/artigo-original',
        ]);

        $resp = $this->get(route('blog.show', 'a-luz-da-caridade'));

        $resp->assertSee('https://externo.exemplo.com/artigo-original', false);
    }

    // -----------------------------------------------------------------------
    // Escape XSS no JSON-LD (JSON_HEX_TAG obrigatório)
    // -----------------------------------------------------------------------

    public function test_jsonld_escapa_tag_de_fechamento_de_script(): void
    {
        // Título com vetor XSS: sem JSON_HEX_TAG o </script> fecha o bloco cedo.
        $this->postPublicado([
            'titulo' => 'Ataque </script> XSS',
            'slug'   => 'ataque-xss-jsonld',
        ]);

        $resp = $this->get(route('blog.show', 'ataque-xss-jsonld'));

        $resp->assertOk();
        // Com JSON_HEX_TAG, o '<' vira '<' — nunca '</script>' literal no HTML.
        $resp->assertSee('<', false);
        $resp->assertDontSee('</script> XSS', false);
    }

    // -----------------------------------------------------------------------
    // Sitemap
    // -----------------------------------------------------------------------

    public function test_sitemap_retorna_200_com_content_type_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_contem_slug_de_post_publicado(): void
    {
        $this->postPublicado(['slug' => 'artigo-publicado']);

        $resp = $this->get('/sitemap.xml');

        $resp->assertSee('artigo-publicado', false);
    }

    public function test_sitemap_nao_contem_slug_de_rascunho(): void
    {
        Post::factory()->create([
            'titulo'          => 'Rascunho Oculto',
            'slug'            => 'rascunho-oculto',
            'status'          => Post::STATUS_RASCUNHO,
            'data_publicacao' => now()->subDay(),
        ]);

        $resp = $this->get('/sitemap.xml');

        $resp->assertDontSee('rascunho-oculto', false);
    }
}
