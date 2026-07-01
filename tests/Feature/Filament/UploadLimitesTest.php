<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Filament;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UploadLimitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_campo_conteudo_aceita_anexo_de_ate_20mb(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (RichEditor $campo): bool => $campo->getFileAttachmentsMaxSize() >= 20480);
    }

    public function test_livewire_aceita_upload_temporario_de_20mb(): void
    {
        $regras = config('livewire.temporary_file_upload.rules');

        $this->assertIsArray($regras);
        $this->assertContains('max:20480', $regras);
    }

    public function test_php_aceita_upload_de_pelo_menos_20mb(): void
    {
        // Roda dentro do container já reconstruído (uploads.ini aplicado).
        $this->assertGreaterThanOrEqual(20 * 1024 * 1024, $this->emBytes(ini_get('upload_max_filesize')));
        $this->assertGreaterThanOrEqual(20 * 1024 * 1024, $this->emBytes(ini_get('post_max_size')));
    }

    private function emBytes(string $valor): int
    {
        $valor = trim($valor);
        $unidade = strtolower($valor[strlen($valor) - 1]);
        $numero = (int) $valor;

        return match ($unidade) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
