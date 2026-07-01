<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Midia;

use App\Models\Biblioteca;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class MidiaRotaTest extends TestCase
{
    use RefreshDatabase;

    private function midiaBiblioteca(): Media
    {
        Storage::fake('public');
        $bytes = UploadedFile::fake()->image('x.png', 800, 600)->getContent();

        return Biblioteca::instance()
            ->addMediaFromString($bytes)
            ->usingFileName('x.png')
            ->toMediaCollection(Biblioteca::COLECAO);
    }

    public function test_serve_web_retorna_200(): void
    {
        $m = $this->midiaBiblioteca();

        $this->get(route('midia.serve', [$m->id, 'web']))->assertOk();
    }

    public function test_midia_inexistente_404(): void
    {
        $this->get(route('midia.serve', [999999, 'web']))->assertNotFound();
    }

    public function test_midia_fora_da_colecao_biblioteca_404(): void
    {
        // #5: mídia de um Post (coleção 'destacada') NÃO é servível por /midia.
        Storage::fake('public');
        $post = Post::factory()->create();
        $m = $post->addMediaFromString(UploadedFile::fake()->image('p.png', 400, 300)->getContent())
            ->usingFileName('p.png')
            ->toMediaCollection(Post::COLECAO_DESTACADA);

        $this->get(route('midia.serve', [$m->id, 'web']))->assertNotFound();
    }

    public function test_conversao_fora_da_allowlist_cai_para_web(): void
    {
        // #11: 'evil' não está na allowlist → serve 'web' (200), não erro.
        $m = $this->midiaBiblioteca();

        $this->get(route('midia.serve', [$m->id, 'evil']))->assertOk();
    }
}
