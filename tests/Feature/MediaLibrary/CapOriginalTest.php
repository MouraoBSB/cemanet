<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\MediaLibrary;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CapOriginalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Imagens de teste podem ser grandes; 256 M garante headroom suficiente
        // sem impactar outros testes (ini_set é restaurado no tearDown do processo).
        ini_set('memory_limit', '256M');
    }

    #[Test]
    public function original_da_colecao_conteudo_e_capado_em_2000px(): void
    {
        // Paisagem 2100×700: largura > teto 2000 com footprint baixo de memória
        // (≈1,5 Mpx) — reduz contenção de GD/optimizer sob carga da suíte completa.
        $post = Post::factory()->create();

        $media = $post
            ->addMediaFromString(
                UploadedFile::fake()->image('grande.jpg', 2100, 700)->get()
            )
            ->usingFileName('grande.jpg')
            ->toMediaCollection(Post::COLECAO_CONTEUDO);

        $dimensoes = @getimagesize($media->getPath());

        $this->assertNotFalse($dimensoes, 'Deve ser possível obter dimensões do arquivo salvo.');
        $this->assertLessThanOrEqual(
            2000,
            $dimensoes[0],
            "Largura do original na coleção conteudo deve ser ≤ 2000px, obteve {$dimensoes[0]}px."
        );
    }

    #[Test]
    public function original_da_colecao_og_e_capado_em_1200px(): void
    {
        $post = Post::factory()->create();

        $media = $post
            ->addMediaFromString(
                UploadedFile::fake()->image('og-grande.jpg', 1600, 900)->get()
            )
            ->usingFileName('og-grande.jpg')
            ->toMediaCollection(Post::COLECAO_OG);

        $dimensoes = @getimagesize($media->getPath());

        $this->assertNotFalse($dimensoes, 'Deve ser possível obter dimensões do arquivo salvo.');
        $this->assertLessThanOrEqual(
            1200,
            $dimensoes[0],
            "Largura do original na coleção og deve ser ≤ 1200px, obteve {$dimensoes[0]}px."
        );
    }

    #[Test]
    public function original_retrato_e_capado_pela_altura(): void
    {
        // Retrato 700×2100: largura 700 (≤ 2000) mas altura 2100 (> 2000). O cap deve
        // agir pelo LADO MAIS LONGO — um cap só por largura deixaria a altura passar.
        $post = Post::factory()->create();

        $media = $post
            ->addMediaFromString(
                UploadedFile::fake()->image('retrato.jpg', 700, 2100)->get()
            )
            ->usingFileName('retrato.jpg')
            ->toMediaCollection(Post::COLECAO_CONTEUDO);

        $dimensoes = @getimagesize($media->getPath());

        $this->assertNotFalse($dimensoes, 'Deve ser possível obter dimensões do arquivo salvo.');
        $this->assertLessThanOrEqual(
            2000,
            $dimensoes[1],
            "Altura do original retrato deve ser ≤ 2000px, obteve {$dimensoes[1]}px."
        );
    }

    #[Test]
    public function imagem_pequena_nao_e_redimensionada(): void
    {
        $post = Post::factory()->create();

        $media = $post
            ->addMediaFromString(
                UploadedFile::fake()->image('small.jpg', 500, 500)->get()
            )
            ->usingFileName('small.jpg')
            ->toMediaCollection(Post::COLECAO_CONTEUDO);

        $dimensoes = @getimagesize($media->getPath());

        $this->assertNotFalse($dimensoes);
        // Deve permanecer em 500px (não foi redimensionada)
        $this->assertSame(500, $dimensoes[0], 'Imagem pequena não deve ser redimensionada.');
    }
}
