<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaixadorImagemTest extends TestCase
{
    public function test_baixa_e_salva_a_imagem(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('bytes-da-imagem', 200, ['Content-Type' => 'image/jpeg'])]);

        $caminho = (new BaixadorImagem)->baixar('https://cemanet.org.br/wp-content/uploads/2025/09/Fulano.jpg', 'fulano');

        $this->assertSame('palestrantes/fulano.jpg', $caminho);
        Storage::disk('public')->assertExists('palestrantes/fulano.jpg');
    }

    public function test_idempotente_nao_rebaixa(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('palestrantes/fulano.jpg', 'ja-existe');
        Http::fake();

        $caminho = (new BaixadorImagem)->baixar('https://x/Fulano.jpg', 'fulano');

        $this->assertSame('palestrantes/fulano.jpg', $caminho);
        Http::assertNothingSent();
    }

    public function test_url_vazia_retorna_null(): void
    {
        Storage::fake('public');
        $this->assertNull((new BaixadorImagem)->baixar(null, 'fulano'));
    }
}
