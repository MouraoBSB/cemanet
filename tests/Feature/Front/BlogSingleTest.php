<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Front;

use App\Models\Categoria;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogSingleTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_publicado_200_com_faq_e_galeria(): void
    {
        Storage::fake('public');

        $cat = Categoria::factory()->create(['slug' => 'reflexoes-e-espiritualidade', 'cor' => '#4E4483']);
        $post = Post::factory()->comGaleria(1)->create(['slug' => 'meu-post', 'status' => 'publicado']);
        $post->categorias()->attach($cat);
        $post->update(['categoria_principal_id' => $cat->id]);
        $post->faqs()->create(['pergunta' => 'P?', 'resposta' => 'R.', 'ordem' => 0]);

        $r = $this->get('/sementeira/meu-post');

        $r->assertOk()->assertSee('P?')->assertSee('lightbox', false);
        $this->assertSame(1, $post->fresh()->visualizacoes);
    }

    public function test_rascunho_e_futuro_dao_404(): void
    {
        Post::factory()->create(['slug' => 'r', 'status' => 'rascunho']);
        Post::factory()->create(['slug' => 'f', 'status' => 'publicado', 'data_publicacao' => now()->addDay()]);

        $this->get('/sementeira/r')->assertNotFound();
        $this->get('/sementeira/f')->assertNotFound();
    }

    public function test_segunda_requisicao_na_mesma_sessao_nao_incrementa(): void
    {
        $post = Post::factory()->create(['slug' => 'idempotente', 'status' => 'publicado', 'visualizacoes' => 0]);

        $this->get('/sementeira/idempotente')->assertOk();
        $this->get('/sementeira/idempotente')->assertOk();

        $this->assertSame(1, $post->fresh()->visualizacoes);
    }

    public function test_hero_renderiza_imagem_destacada(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create([
            'slug'   => 'com-capa',
            'status' => 'publicado',
        ]);

        $url = $post->getFirstMediaUrl(Post::COLECAO_DESTACADA, 'web');

        $this->get('/sementeira/com-capa')
            ->assertOk()
            ->assertSee($url, false);
    }

    public function test_capa_aparece_no_heroi_e_no_corpo(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create([
            'slug'   => 'capa-dupla',
            'status' => 'publicado',
        ]);

        $url = $post->getFirstMediaUrl(Post::COLECAO_DESTACADA, 'web');

        $html = $this->get('/sementeira/capa-dupla')->assertOk()->getContent();

        // a URL da conversão 'web' entra no fundo do herói E como imagem de abertura no corpo
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, $url),
            'A imagem de capa deve aparecer no herói e no corpo da reportagem.'
        );
    }
}
