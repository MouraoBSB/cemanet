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

    public function test_nao_autenticado_e_redirecionado_e_nao_registra_midia(): void
    {
        Storage::fake('public');

        // Sem auth: o middleware Authenticate do painel redireciona ao login (prova que a
        // rota está protegida) e o controller nem roda → nada é registrado.
        $this->post($this->url(), [
            'imagem' => UploadedFile::fake()->image('paste.png', 400, 300),
        ])->assertRedirect();

        $this->assertSame(0, Biblioteca::instance()->getMedia(Biblioteca::COLECAO)->count());
    }

    public function test_rejeita_arquivo_que_nao_e_imagem_com_422_json(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->post($this->url(), [
            'imagem' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['imagem']]);

        $this->assertSame(0, Biblioteca::instance()->getMedia(Biblioteca::COLECAO)->count());
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
