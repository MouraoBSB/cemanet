<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Filament;

use App\Filament\Resources\Bibliotecas\BibliotecaResource;
use App\Filament\Resources\Bibliotecas\Pages\ListBibliotecas;
use App\Models\Biblioteca;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BibliotecaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->actingAs(User::factory()->create());
    }

    /** Cria uma mídia na biblioteca para uso nos testes. */
    private function criarMidia(): \Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        return Biblioteca::instance()
            ->addMediaFromString(UploadedFile::fake()->image('x.png', 800, 600)->getContent())
            ->usingFileName('x.png')
            ->toMediaCollection(Biblioteca::COLECAO);
    }

    public function test_listagem_renderiza(): void
    {
        $this->get(BibliotecaResource::getUrl('index'))->assertOk();
    }

    public function test_delecao_bloqueada_quando_em_uso(): void
    {
        $m = $this->criarMidia();

        Post::factory()->create([
            'conteudo' => '<p>x</p><img src="/midia/' . $m->id . '/web" alt="">',
        ]);

        Livewire::test(ListBibliotecas::class)
            ->callTableAction('delete', $m);

        $this->assertDatabaseHas('media', ['id' => $m->id]);
    }

    public function test_delecao_livre_exclui(): void
    {
        $m = $this->criarMidia();

        Livewire::test(ListBibliotecas::class)
            ->callTableAction('delete', $m);

        $this->assertDatabaseMissing('media', ['id' => $m->id]);
    }

    public function test_fronteira_do_like(): void
    {
        $m = $this->criarMidia();

        // Referência a um ID com sufixo (ex: 129 quando o id é 12) — não deve bloquear
        Post::factory()->create([
            'conteudo' => '<img src="/midia/' . $m->id . '9/web" alt="">',
        ]);

        Livewire::test(ListBibliotecas::class)
            ->callTableAction('delete', $m);

        $this->assertDatabaseMissing('media', ['id' => $m->id]);
    }
}
