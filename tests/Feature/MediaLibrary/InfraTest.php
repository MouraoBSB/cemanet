<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\MediaLibrary;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tests\TestCase;

class InfraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria tabela temporária para o model de teste
        Schema::create('itens_de_teste_media', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('itens_de_teste_media');
        parent::tearDown();
    }

    #[Test]
    public function tabela_media_existe(): void
    {
        $this->assertTrue(
            Schema::hasTable('media'),
            'A tabela "media" deve existir após a migration do Spatie Media Library.'
        );
    }

    #[Test]
    public function model_com_has_media_persiste_arquivo_no_storage(): void
    {
        Storage::fake('public');

        $item = ItemDeTesteMedia::create();

        $media = $item
            ->addMediaFromString('conteudo-de-teste')
            ->usingFileName('teste.txt')
            ->toMediaCollection('default');

        // Deve haver um registro na tabela media
        $this->assertDatabaseCount('media', 1);

        // O caminho gerado não pode ser vazio
        $this->assertNotEmpty($media->getPath());
    }
}

/**
 * Model descartável usado apenas para validar a infraestrutura do Media Library.
 * Não registra conversões — evita dependência dos binários otimizadores no teste.
 */
class ItemDeTesteMedia extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'itens_de_teste_media';
    protected $guarded = [];
}
