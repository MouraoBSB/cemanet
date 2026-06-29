<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace Tests\Feature\Front;

use App\Models\Biblioteca;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogConteudoRicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_renderiza_figcaption_e_image_object_para_imagem_com_legenda(): void
    {
        Storage::fake('public');

        $biblioteca = Biblioteca::instance();
        $biblioteca->addMediaFromString(UploadedFile::fake()->image('f.jpg', 200, 200)->getContent())
            ->usingFileName('f.jpg')
            ->toMediaCollection(Biblioteca::COLECAO);

        $midia = $biblioteca->getFirstMedia(Biblioteca::COLECAO);
        $midia->setCustomProperty('legenda', 'Legenda de teste')->save();

        $post = Post::factory()->create([
            'status'          => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDay(),
            'conteudo'        => '<p>texto</p><img src="/midia/' . $midia->id . '/web" alt="x">',
        ]);

        $resp = $this->get(route('blog.show', $post->slug));

        $resp->assertOk();
        $resp->assertSee('<figcaption>Legenda de teste</figcaption>', escape: false);
        $resp->assertSee('"ImageObject"', escape: false);   // no JSON-LD
        $resp->assertSee('Legenda de teste');                // caption no ImageObject
    }
}
