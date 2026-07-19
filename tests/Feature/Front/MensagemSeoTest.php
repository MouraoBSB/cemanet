<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MensagemSeoTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido do blog). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    /** Extrai e decodifica o bloco JSON-LD do HTML renderizado. */
    private function extrairJsonLd(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m, 'Bloco JSON-LD não encontrado no HTML.');

        $dados = json_decode(trim($m[1]), true);
        $this->assertIsArray($dados, 'JSON-LD não é um JSON válido.');

        return $dados;
    }

    // -----------------------------------------------------------------------
    // Canonical
    // -----------------------------------------------------------------------

    public function test_canonical_presente_e_igual_a_rota_da_mensagem(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'paz-e-luz']);

        $resp = $this->get(route('mensagens.show', 'paz-e-luz'));

        $resp->assertOk();
        $resp->assertSee('rel="canonical"', false);
        $resp->assertSee('href="'.route('mensagens.show', 'paz-e-luz').'"', false);
    }

    // -----------------------------------------------------------------------
    // og:image condicional
    // -----------------------------------------------------------------------

    public function test_og_image_presente_quando_pictografia_com_midia_anexada(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create(['slug' => 'pict-com-midia', 'formato' => 'pictografia']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('desenho.png')
            ->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);

        $resp = $this->get(route('mensagens.show', 'pict-com-midia'));

        $resp->assertOk();
        $resp->assertSee('property="og:image"', false);
    }

    public function test_og_image_ausente_quando_psicografia_sem_midia(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'psico-sem-midia', 'formato' => 'psicografia']);

        $resp = $this->get(route('mensagens.show', 'psico-sem-midia'));

        $resp->assertOk();
        $resp->assertDontSee('property="og:image"', false);
    }

    // -----------------------------------------------------------------------
    // JSON-LD CreativeWork
    // -----------------------------------------------------------------------

    public function test_jsonld_e_parseavel_e_do_tipo_creativework(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'jsonld-ok', 'titulo' => 'Mensagem de Prova']);

        $resp = $this->get(route('mensagens.show', 'jsonld-ok'));

        $resp->assertOk();
        $dados = $this->extrairJsonLd($resp->getContent());

        $this->assertSame('CreativeWork', $dados['@type']);
        $this->assertSame('Mensagem de Prova', $dados['name']);
        $this->assertSame(route('mensagens.show', 'jsonld-ok'), $dados['url']);
    }
}
