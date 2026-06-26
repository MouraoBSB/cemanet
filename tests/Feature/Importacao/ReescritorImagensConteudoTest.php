<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Importacao;

use App\Importacao\ReescritorImagensConteudo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReescritorImagensConteudoTest extends TestCase
{
    public function test_reescreve_e_baixa_imagens_do_conteudo(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('binario', 200)]);

        $html = '<p>x</p><img src="https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg" alt="a">';
        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post');

        $this->assertStringNotContainsString('cemanet.org.br/wp-content', $out);
        $this->assertStringContainsString('/storage/blog/conteudo/', $out);
        Storage::disk('public')->assertExists(Str::after(Str::before($out, '" alt'), '/storage/'));
    }

    public function test_mantem_url_original_em_caso_de_falha(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 404)]);

        $url = 'https://cemanet.org.br/wp-content/uploads/2025/01/inexistente.jpg';
        $html = "<img src=\"{$url}\" alt=\"b\">";
        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post');

        $this->assertStringContainsString($url, $out);
    }

    public function test_nao_altera_imagens_externas_sem_wp_content(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 200)]);

        $url = 'https://outro-site.com/imagem.jpg';
        $html = "<img src=\"{$url}\" alt=\"c\">";
        $out = app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post');

        $this->assertStringContainsString($url, $out);
        Http::assertNothingSent();
    }

    public function test_idempotente_nao_rebaixa_imagem_ja_existente(): void
    {
        Storage::fake('public');

        $url = 'https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg';
        $hash = md5($url);
        Storage::disk('public')->put("blog/conteudo/{$hash}.jpg", 'ja-existe');
        Http::fake();

        $html = "<img src=\"{$url}\" alt=\"d\">";
        app(ReescritorImagensConteudo::class)->reescrever($html, 'meu-post');

        Http::assertNothingSent();
    }
}
