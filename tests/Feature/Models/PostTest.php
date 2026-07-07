<?php

namespace Tests\Feature\Models;

use App\Models\Categoria;
use App\Models\Post;
use App\Models\PostFaq;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_post_com_casts(): void
    {
        $post = Post::factory()->create([
            'data_publicacao' => '2026-01-15 10:00:00',
            'destaque' => true,
            'robots_noindex' => false,
            'visualizacoes' => 42,
            'tempo_leitura_min' => 5,
        ]);

        $this->assertInstanceOf(Carbon::class, $post->data_publicacao);
        $this->assertTrue($post->destaque);
        $this->assertFalse($post->robots_noindex);
        $this->assertSame(42, $post->visualizacoes);
    }

    public function test_escopo_publicado_exclui_rascunho(): void
    {
        Post::factory()->create(['status' => Post::STATUS_PUBLICADO, 'data_publicacao' => now()->subDay()]);
        Post::factory()->rascunho()->create();

        $this->assertCount(1, Post::publicado()->get());
    }

    public function test_escopo_publicado_exclui_data_futura(): void
    {
        Post::factory()->create(['status' => Post::STATUS_PUBLICADO, 'data_publicacao' => now()->subDay()]);
        Post::factory()->agendado()->create(); // status publicado mas data no futuro

        $publicados = Post::publicado()->get();
        $this->assertCount(1, $publicados);
    }

    public function test_escopo_mais_lidas_ordena_por_visualizacoes(): void
    {
        Post::factory()->create(['status' => Post::STATUS_PUBLICADO, 'data_publicacao' => now()->subDay(), 'visualizacoes' => 10]);
        Post::factory()->create(['status' => Post::STATUS_PUBLICADO, 'data_publicacao' => now()->subDay(), 'visualizacoes' => 100]);
        Post::factory()->create(['status' => Post::STATUS_PUBLICADO, 'data_publicacao' => now()->subDay(), 'visualizacoes' => 50]);

        $lista = Post::maisLidas()->get();
        $this->assertSame(100, $lista->first()->visualizacoes);
        $this->assertSame(10, $lista->last()->visualizacoes);
    }

    public function test_relacao_categorias(): void
    {
        $post = Post::factory()->create();
        $cat = Categoria::factory()->create();
        $post->categorias()->attach($cat);

        $this->assertCount(1, $post->categorias);
        $this->assertSame($cat->id, $post->categorias->first()->id);
    }

    public function test_relacao_tags(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();
        $post->tags()->attach($tag);

        $this->assertCount(1, $post->tags);
        $this->assertSame($tag->id, $post->tags->first()->id);
    }

    public function test_relacao_faqs_ordenada(): void
    {
        $post = Post::factory()->create();
        PostFaq::create(['post_id' => $post->id, 'pergunta' => 'B?', 'resposta' => 'R', 'ordem' => 2]);
        PostFaq::create(['post_id' => $post->id, 'pergunta' => 'A?', 'resposta' => 'R', 'ordem' => 1]);

        $faqs = $post->faqs()->get();
        $this->assertCount(2, $faqs);
        $this->assertSame('A?', $faqs->first()->pergunta);
    }

    public function test_galeria_media_ordenada(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        $img1 = UploadedFile::fake()->image('primeira.jpg', 100, 100)->getContent();
        $img2 = UploadedFile::fake()->image('segunda.jpg', 100, 100)->getContent();

        $post->addMediaFromString($img1)->usingFileName('primeira.jpg')->toMediaCollection(Post::COLECAO_GALERIA);
        $post->addMediaFromString($img2)->usingFileName('segunda.jpg')->toMediaCollection(Post::COLECAO_GALERIA);

        $galeria = $post->getMedia(Post::COLECAO_GALERIA);
        $this->assertCount(2, $galeria);
        // O original é reencodado para WebP no upload (padrão único de imagens),
        // então o file_name passa a terminar em .webp preservando a ordem de inserção.
        $this->assertSame('primeira.webp', $galeria->first()->file_name);
        $this->assertSame('segunda.webp', $galeria->last()->file_name);
    }

    public function test_cor_categoria_retorna_cor_da_principal(): void
    {
        $cat = Categoria::factory()->create(['cor' => '#FF0000']);
        $post = Post::factory()->create(['categoria_principal_id' => $cat->id]);

        $this->assertSame('#FF0000', $post->corCategoria);
    }

    public function test_cor_categoria_fallback_sem_principal(): void
    {
        $post = Post::factory()->create(['categoria_principal_id' => null]);

        $this->assertSame('#7A8A8A', $post->corCategoria);
    }
}
