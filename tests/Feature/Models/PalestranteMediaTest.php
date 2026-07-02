<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class PalestranteMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_palestrante_implementa_has_media(): void
    {
        $this->assertInstanceOf(HasMedia::class, new Palestrante);
    }

    public function test_foto_url_nula_sem_midia(): void
    {
        $p = Palestrante::factory()->create();

        $this->assertNull($p->foto_url);
        $this->assertNull($p->foto_thumb_url);
    }

    public function test_foto_url_retorna_webp_com_midia(): void
    {
        Storage::fake('public');
        $p = Palestrante::factory()->create();

        $p->addMediaFromString(UploadedFile::fake()->image('f.png', 800, 600)->getContent())
            ->usingFileName('f.png')
            ->toMediaCollection(Palestrante::COLECAO_FOTO);

        $this->assertNotNull($p->foto_url);
        $this->assertStringContainsString('.webp', $p->foto_url);
    }

    public function test_original_armazenado_e_webp_e_gera_conversoes(): void
    {
        Storage::fake('public');
        $p = Palestrante::factory()->create();

        $p->addMediaFromString(UploadedFile::fake()->image('f.png', 1000, 800)->getContent())
            ->usingFileName('f.png')
            ->toMediaCollection(Palestrante::COLECAO_FOTO);

        $media = $p->getFirstMedia(Palestrante::COLECAO_FOTO);

        // O original guardado é WebP (nada de PNG/JPEG "gordo" no disco).
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->file_name);
        $this->assertFileExists($media->getPath());

        // As conversões continuam sendo geradas a partir do original (WebP).
        $this->assertStringContainsString('.webp', $p->foto_url);
        $this->assertStringContainsString('.webp', $p->foto_thumb_url);
        $this->assertFileExists($media->getPath('web'));
    }
}
