<?php

namespace Tests\Feature\Models;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coleções_e_url_da_destacada(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        // PNG: GD suporta sem imagejpeg; conversões decodificam corretamente
        $img = UploadedFile::fake()->image('a.png', 800, 600)->getContent();

        $post->addMediaFromString($img)
            ->usingFileName('a.png')
            ->toMediaCollection(Post::COLECAO_DESTACADA);

        $this->assertNotEmpty($post->getFirstMediaUrl(Post::COLECAO_DESTACADA));

        // singleFile() deve substituir — apenas 1 item na coleção
        $img2 = UploadedFile::fake()->image('b.png', 800, 600)->getContent();
        $post->addMediaFromString($img2)
            ->usingFileName('b.png')
            ->toMediaCollection(Post::COLECAO_DESTACADA);

        $this->assertCount(1, $post->getMedia(Post::COLECAO_DESTACADA));
    }

    public function test_colecao_galeria_aceita_multiplos_arquivos(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        $img1 = UploadedFile::fake()->image('g1.png', 800, 600)->getContent();
        $img2 = UploadedFile::fake()->image('g2.png', 800, 600)->getContent();

        $post->addMediaFromString($img1)->usingFileName('g1.png')->toMediaCollection(Post::COLECAO_GALERIA);
        $post->addMediaFromString($img2)->usingFileName('g2.png')->toMediaCollection(Post::COLECAO_GALERIA);

        $this->assertCount(2, $post->getMedia(Post::COLECAO_GALERIA));
    }

    public function test_colecao_og_e_single_file(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        $img1 = UploadedFile::fake()->image('og1.png', 1200, 630)->getContent();
        $img2 = UploadedFile::fake()->image('og2.png', 1200, 630)->getContent();

        $post->addMediaFromString($img1)->usingFileName('og1.png')->toMediaCollection(Post::COLECAO_OG);
        $post->addMediaFromString($img2)->usingFileName('og2.png')->toMediaCollection(Post::COLECAO_OG);

        $this->assertCount(1, $post->getMedia(Post::COLECAO_OG));
    }

    public function test_consts_das_colecoes_estao_definidas(): void
    {
        $this->assertSame('destacada', Post::COLECAO_DESTACADA);
        $this->assertSame('galeria', Post::COLECAO_GALERIA);
        $this->assertSame('og', Post::COLECAO_OG);
        $this->assertSame('conteudo', Post::COLECAO_CONTEUDO);
    }

    public function test_accessor_imagem_destacada_url_retorna_null_sem_midia(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        $this->assertNull($post->imagem_destacada_url);
    }

    public function test_accessor_imagem_destacada_url_retorna_url_com_midia(): void
    {
        Storage::fake('public');

        $post = Post::factory()->create();

        $img = UploadedFile::fake()->image('hero.png', 800, 600)->getContent();
        $post->addMediaFromString($img)
            ->usingFileName('hero.png')
            ->toMediaCollection(Post::COLECAO_DESTACADA);

        $this->assertNotNull($post->imagem_destacada_url);
        $this->assertNotEmpty($post->imagem_destacada_url);
    }

    public function test_post_implementa_has_media(): void
    {
        $post = new Post;

        $this->assertInstanceOf(\Spatie\MediaLibrary\HasMedia::class, $post);
    }

    public function test_colunas_imagem_antigas_removidas_do_schema(): void
    {
        $this->assertFalse(Schema::hasColumn('posts', 'imagem_destacada'));
        $this->assertFalse(Schema::hasColumn('posts', 'og_imagem'));
        $this->assertTrue(Schema::hasColumn('posts', 'imagem_destacada_alt'));
        $this->assertFalse(Schema::hasTable('post_imagens'));
    }
}
