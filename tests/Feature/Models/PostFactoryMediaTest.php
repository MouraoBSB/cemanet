<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Models;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Testa os states de mídia da PostFactory (comImagemDestacada / comGaleria).
 */
class PostFactoryMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_com_imagem_destacada_anexa_exatamente_uma_midia_na_colecao(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create();

        $this->assertCount(1, $post->getMedia(Post::COLECAO_DESTACADA));
    }

    public function test_com_imagem_destacada_url_nao_vazia(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create();

        $this->assertNotEmpty($post->getFirstMediaUrl(Post::COLECAO_DESTACADA));
    }

    public function test_com_galeria_anexa_n_imagens_na_colecao(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comGaleria(3)->create();

        $this->assertCount(3, $post->getMedia(Post::COLECAO_GALERIA));
    }

    public function test_com_galeria_padrao_anexa_duas_imagens(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comGaleria()->create();

        $this->assertCount(2, $post->getMedia(Post::COLECAO_GALERIA));
    }

    public function test_com_galeria_nao_afeta_colecao_destacada(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comGaleria(2)->create();

        $this->assertCount(0, $post->getMedia(Post::COLECAO_DESTACADA));
    }

    public function test_com_imagem_destacada_nao_afeta_colecao_galeria(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create();

        $this->assertCount(0, $post->getMedia(Post::COLECAO_GALERIA));
    }
}
