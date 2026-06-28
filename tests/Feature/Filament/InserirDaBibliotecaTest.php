<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Filament;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Biblioteca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InserirDaBibliotecaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserir_existente_dispara_comando_com_url_portavel(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $media = Biblioteca::instance()
            ->addMediaFromString(UploadedFile::fake()->image('x.png', 800, 600)->getContent())
            ->usingFileName('x.png')
            ->toMediaCollection(Biblioteca::COLECAO);

        Livewire::test(CreatePost::class)
            ->callFormComponentAction('conteudo', 'inserirDaBiblioteca',
                data: ['midia_id' => $media->id],
                arguments: ['editorSelection' => null])
            ->assertDispatched('run-rich-editor-commands');
    }
}
