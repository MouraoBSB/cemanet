<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Importacao;

use App\Importacao\ReescritorImagensConteudo;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReescritorImagensConteudoTest extends TestCase
{
    use RefreshDatabase;

    /** Gera bytes de uma imagem JPEG real decodável pelo GD/getimagesize. */
    private function imagemBytes(string $nome = 'img.jpg', int $w = 800, int $h = 600): string
    {
        return UploadedFile::fake()->image($nome, $w, $h)->getContent();
    }

    public function test_reescreve_e_baixa_imagens_do_conteudo(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->imagemBytes(), 200)]);

        $post = Post::factory()->create();

        $url = 'https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg';
        $html = "<p>x</p><img src=\"{$url}\" alt=\"a\">";

        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post', $post);

        // URL legada substituída
        $this->assertStringNotContainsString('cemanet.org.br/wp-content', $out);

        // data-id injetado
        $this->assertMatchesRegularExpression('/data-id="\d+"/', $out);

        // Mídia gravada na coleção correta
        $this->assertCount(1, $post->getMedia(Post::COLECAO_CONTEUDO));

        // URL no HTML aponta para a conversão 'web' da mídia
        $media = $post->getFirstMedia(Post::COLECAO_CONTEUDO);
        $this->assertStringContainsString($media->getUrl('web'), $out);
    }

    public function test_mantem_url_original_em_caso_de_falha(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 404)]);

        $post = Post::factory()->create();

        $url = 'https://cemanet.org.br/wp-content/uploads/2025/01/inexistente.jpg';
        $html = "<img src=\"{$url}\" alt=\"b\">";
        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post', $post);

        $this->assertStringContainsString($url, $out);
        $this->assertCount(0, $post->getMedia(Post::COLECAO_CONTEUDO));
    }

    public function test_nao_altera_imagens_externas_sem_wp_content(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 200)]);

        $post = Post::factory()->create();

        $url = 'https://outro-site.com/imagem.jpg';
        $html = "<img src=\"{$url}\" alt=\"c\">";
        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post', $post);

        $this->assertStringContainsString($url, $out);
        Http::assertNothingSent();
    }

    public function test_idempotente_nao_duplica_imagens_na_colecao(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->imagemBytes(), 200)]);

        $post = Post::factory()->create();

        $url = 'https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg';
        $html = "<img src=\"{$url}\" alt=\"d\">";

        $reescritor = app(ReescritorImagensConteudo::class);

        // Chama 2× — clearMediaCollection no início de cada chamada garante 1 item
        $reescritor->reescrever($html, 'meu-post', $post);
        $reescritor->reescrever($html, 'meu-post', $post);

        $post->refresh();
        $this->assertCount(1, $post->getMedia(Post::COLECAO_CONTEUDO));
    }
}
