<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Front;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogMediaRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Post com imagem destacada anexada via Media Library deve exibir
     * a URL real da conversão 'web' tanto no single quanto na listagem.
     */
    public function test_single_exibe_url_da_media_library(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create([
            'slug'   => 'post-com-media',
            'status' => Post::STATUS_PUBLICADO,
        ]);

        $url = $post->getFirstMediaUrl(Post::COLECAO_DESTACADA, 'web');

        $this->get('/sementeira/post-com-media')
            ->assertOk()
            ->assertSee($url, false);
    }

    /**
     * Listagem exibe a URL da Media Library no herói do destaque.
     */
    public function test_listagem_exibe_url_da_media_library_no_heroi(): void
    {
        Storage::fake('public');

        $post = Post::factory()->comImagemDestacada()->create([
            'status'   => Post::STATUS_PUBLICADO,
            'destaque' => true,
        ]);

        $url = $post->getFirstMediaUrl(Post::COLECAO_DESTACADA, 'web');

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee($url, false);
    }

    /**
     * Post sem nenhuma mídia anexada não deve quebrar (200) e não deve
     * gerar uma tag <img> com src vazio ou caminho parcial (ex.: 'storage/').
     */
    public function test_single_sem_media_nao_quebra(): void
    {
        Storage::fake('public');

        Post::factory()->create([
            'slug'   => 'post-sem-media',
            'status' => Post::STATUS_PUBLICADO,
        ]);

        $resp = $this->get('/sementeira/post-sem-media')->assertOk();

        // Sem mídia não deve haver img com src vazio ou terminando só em 'storage/'
        $resp->assertDontSee('src=""', false);
        $resp->assertDontSee('src="storage/"', false);
    }
}
