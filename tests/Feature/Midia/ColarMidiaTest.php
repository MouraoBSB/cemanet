<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Midia;

use App\Models\Biblioteca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ColarMidiaTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        return '/admin/midia/colar';
    }

    public function test_admin_autenticado_cola_imagem_e_recebe_url_portavel(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $resp = $this->post($this->url(), [
            'imagem' => UploadedFile::fake()->image('paste.png', 800, 600),
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['id', 'url']);

        $url = $resp->json('url');
        $this->assertStringContainsString('/midia/', $url);
        $this->assertStringEndsWith('/web', $url);
        $this->assertStringStartsWith('/midia/', $url); // relativa, sem domínio
        $this->assertCount(1, Biblioteca::instance()->getMedia(Biblioteca::COLECAO));
    }

    public function test_nao_autenticado_nao_registra_midia(): void
    {
        Storage::fake('public');

        $this->post($this->url(), [
            'imagem' => UploadedFile::fake()->image('paste.png', 400, 300),
        ]);

        // Filament redireciona p/ login; o importante: NADA é registrado.
        $this->assertSame(0, Biblioteca::instance()->getMedia(Biblioteca::COLECAO)->count());
    }

    public function test_rejeita_arquivo_que_nao_e_imagem(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        // Envia com Accept JSON para forçar resposta 422 (em vez de redirect de sessão).
        $resp = $this->withHeaders(['Accept' => 'application/json'])
            ->post($this->url(), [
                'imagem' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ]);

        $this->assertNotSame(200, $resp->getStatusCode());
        $this->assertSame(0, \App\Models\Biblioteca::instance()->getMedia(\App\Models\Biblioteca::COLECAO)->count());
    }

    public function test_dedup_mesma_imagem_retorna_mesma_url(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        // Mesmo conteúdo de bytes nas duas chamadas.
        $bytes = UploadedFile::fake()->image('a.png', 800, 600)->getContent();
        $fazer = fn (string $nome) => $this->post($this->url(), [
            'imagem' => UploadedFile::fake()->createWithContent($nome, $bytes),
        ]);

        $u1 = $fazer('a.png')->json('url');
        $u2 = $fazer('b.png')->json('url');

        $this->assertSame($u1, $u2);
        $this->assertCount(1, Biblioteca::instance()->getMedia(Biblioteca::COLECAO));
    }
}
