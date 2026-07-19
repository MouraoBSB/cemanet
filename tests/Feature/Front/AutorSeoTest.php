<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutorSeoTest extends TestCase
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

    public function test_canonical_presente_e_igual_a_rota_do_perfil(): void
    {
        AutorEspiritual::factory()->ativo()->create(['slug' => 'emmanuel']);

        $resp = $this->get(route('autores.show', 'emmanuel'));

        $resp->assertOk();
        $resp->assertSee('rel="canonical"', false);
        $resp->assertSee('href="'.route('autores.show', 'emmanuel').'"', false);
    }

    // -----------------------------------------------------------------------
    // og:image condicional (foto_url)
    // -----------------------------------------------------------------------

    public function test_og_image_presente_quando_autor_tem_foto(): void
    {
        Storage::fake('public');
        $a = AutorEspiritual::factory()->ativo()->create(['slug' => 'com-foto']);
        $a->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('foto.png')
            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);

        $resp = $this->get(route('autores.show', 'com-foto'));
        $urlWeb = $a->fresh()->foto_url;

        $resp->assertOk();
        $resp->assertSee('property="og:image"', false);
        $resp->assertSee('content="'.$urlWeb.'"', false);
    }

    public function test_og_image_ausente_quando_autor_sem_foto(): void
    {
        AutorEspiritual::factory()->ativo()->create(['slug' => 'sem-foto']);

        $resp = $this->get(route('autores.show', 'sem-foto'));

        $resp->assertOk();
        $resp->assertDontSee('property="og:image"', false);
    }

    // -----------------------------------------------------------------------
    // JSON-LD Person
    // -----------------------------------------------------------------------

    public function test_jsonld_e_parseavel_e_do_tipo_person(): void
    {
        AutorEspiritual::factory()->ativo()->create(['slug' => 'jsonld-ok', 'nome' => 'Bezerra de Menezes']);

        $resp = $this->get(route('autores.show', 'jsonld-ok'));

        $resp->assertOk();
        $dados = $this->extrairJsonLd($resp->getContent());

        $this->assertSame('Person', $dados['@type']);
        $this->assertSame('Bezerra de Menezes', $dados['name']);
        $this->assertSame(route('autores.show', 'jsonld-ok'), $dados['url']);
    }
}
