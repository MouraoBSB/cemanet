<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituais;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorAutoresEspirituaisTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    private function leitor(array $autores): LeitorAutoresEspirituais
    {
        return new class($autores) implements LeitorAutoresEspirituais
        {
            public function __construct(private array $autores) {}

            public function autores(): array
            {
                return $this->autores;
            }
        };
    }

    private function baixador(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixarCapado(?string $url, int $teto = 2000): ?string
            {
                return $url ? base64_decode(ImportadorAutoresEspirituaisTest::pngBytes()) : null;
            }
        };
    }

    public static function pngBytes(): string
    {
        return self::PNG_1X1;
    }

    private function autorLegado(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'bezerra-de-menezes',
            'nome' => 'Bezerra de Menezes',
            'bio' => '<p>Médico dos pobres.</p>',
            'foto_url' => 'https://legado.example/wp-content/uploads/bezerra.jpg',
        ], $overrides);
    }

    private function importar(array $autores): array
    {
        return (new ImportadorAutoresEspirituais($this->leitor($autores), $this->baixador()))->importar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_mapeia_nome_slug_bio_e_foto(): void
    {
        $this->importar([$this->autorLegado()]);

        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $this->assertNotNull($autor);
        $this->assertSame('Bezerra de Menezes', $autor->nome);
        $this->assertStringContainsString('Médico dos pobres', (string) $autor->bio);
        $this->assertTrue($autor->hasMedia(AutorEspiritual::COLECAO_FOTO));
        $this->assertNull($autor->chamada);   // legado não tem chamada
        $this->assertTrue($autor->ativo);      // default true
    }

    public function test_bio_vazia_vira_null(): void
    {
        $this->importar([$this->autorLegado(['slug' => 'irma-marta', 'bio' => null, 'foto_url' => null])]);

        $this->assertNull(AutorEspiritual::firstWhere('slug', 'irma-marta')->bio);
    }

    public function test_autor_sem_thumbnail_fica_sem_midia_sem_erro(): void
    {
        $resumo = $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);

        $autor = AutorEspiritual::firstWhere('slug', 'abilio');
        $this->assertFalse($autor->hasMedia(AutorEspiritual::COLECAO_FOTO));
        $this->assertSame(1, $resumo['contadores']['sem_thumbnail']);
    }

    public function test_nao_sincroniza_departamentos(): void
    {
        $this->importar([$this->autorLegado(['foto_url' => null])]);

        $this->assertSame(0, AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes')->departamentos()->count());
    }

    public function test_e_idempotente(): void
    {
        $this->importar([$this->autorLegado()]);
        $this->importar([$this->autorLegado()]);

        $this->assertSame(1, AutorEspiritual::count());
        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $this->assertSame(1, $autor->getMedia(AutorEspiritual::COLECAO_FOTO)->count()); // não duplicou
    }

    public function test_reimport_preserva_chamada_e_ativo_do_admin(): void
    {
        $this->importar([$this->autorLegado(['foto_url' => null])]);

        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $autor->update(['chamada' => 'O médico dos pobres.', 'ativo' => false]);   // curadoria do admin

        $this->importar([$this->autorLegado(['foto_url' => null])]);   // re-import (legado sem chamada/ativo)

        $autor->refresh();
        $this->assertSame('O médico dos pobres.', $autor->chamada);
        $this->assertFalse($autor->ativo);
    }

    public function test_reimport_de_autor_sem_thumbnail_preserva_foto_do_admin(): void
    {
        // O1: o clobber que o molde de Eventos faria.
        $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);
        $autor = AutorEspiritual::firstWhere('slug', 'abilio');

        $autor->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('manual.png')
            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO));

        $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);   // re-import sem thumbnail

        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO), 'a foto do /admin foi apagada — clobber de mídia (O1)');
    }
}
