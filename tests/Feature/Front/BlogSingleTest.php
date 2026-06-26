<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Front;

use App\Models\Categoria;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSingleTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_publicado_200_com_faq_e_galeria(): void
    {
        $cat = Categoria::factory()->create(['slug' => 'reflexoes-e-espiritualidade', 'cor' => '#4E4483']);
        $post = Post::factory()->create(['slug' => 'meu-post', 'status' => 'publicado']);
        $post->categorias()->attach($cat);
        $post->update(['categoria_principal_id' => $cat->id]);
        $post->faqs()->create(['pergunta' => 'P?', 'resposta' => 'R.', 'ordem' => 0]);
        $post->imagens()->create(['caminho' => 'blog/galeria/x.jpg', 'ordem' => 0]);

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
        Post::factory()->create([
            'slug' => 'com-capa',
            'status' => 'publicado',
            'imagem_destacada' => 'blog/destacada/com-capa.jpg',
        ]);

        $this->get('/sementeira/com-capa')
            ->assertOk()
            ->assertSee('storage/blog/destacada/com-capa.jpg', false);
    }
}
