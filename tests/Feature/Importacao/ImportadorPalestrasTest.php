<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorPalestras;
use App\Importacao\LeitorLegado;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorPalestrasTest extends TestCase
{
    use RefreshDatabase;

    private function leitorFake(): LeitorLegado
    {
        return new class implements LeitorLegado
        {
            public function assuntos(): array
            {
                return [
                    ['nome' => 'Espiritismo', 'slug' => 'espiritismo', 'parent_slug' => null],
                    ['nome' => 'Fé', 'slug' => 'fe', 'parent_slug' => 'espiritismo'],
                ];
            }

            public function palestrantes(): array
            {
                return [
                    ['nome' => 'Ana', 'slug' => 'ana', 'bio' => '<p>bio</p>', 'email' => 'ana@x.org', 'telefone' => null, 'mostrar_email' => true, 'mostrar_telefone' => false, 'ativo' => true, 'foto_url' => 'https://x/ana.jpg'],
                    ['nome' => 'Diretor Bruno', 'slug' => 'bruno', 'bio' => null, 'email' => null, 'telefone' => null, 'mostrar_email' => false, 'mostrar_telefone' => false, 'ativo' => false, 'foto_url' => null],
                ];
            }

            public function palestras(): array
            {
                return [[
                    'titulo' => 'Auxílios do Invisível', 'slug' => 'auxilios', 'subtitulo' => 'sub', 'resumo' => 'res',
                    'descricao' => '<p>corpo</p>', 'data_da_palestra' => Carbon::parse('2026-06-28 16:00:00'),
                    'online' => true, 'link_youtube' => 'https://youtube.com/live/abc', 'cor_fundo' => '#89ab98',
                    'publico_online' => 10, 'publico_presencial' => 20, 'publico_total' => 30, 'status' => 'publicado',
                    'palestrantes_slugs' => ['ana'], 'diretor_slug' => 'bruno', 'assuntos_slugs' => ['fe'],
                    'destaques' => [['destaque' => 'Fé', 'texto' => 'sobre fé', 'ordem' => 0]],
                ]];
            }
        };
    }

    /**
     * Stub do BaixadorImagem que grava um JPEG mínimo válido no disco 'public' fake e
     * retorna o caminho relativo, evitando HTTP real e bytes inválidos nas conversões do Spatie.
     */
    private function baixadorFake(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixar(?string $url, string $slug): ?string
            {
                if (empty($url)) {
                    return null;
                }
                $caminho = "palestrantes/{$slug}.jpg";
                // Grava um JPEG mínimo válido (1×1 px) para que o Spatie consiga processar.
                Storage::disk('public')->put(
                    $caminho,
                    UploadedFile::fake()->image("{$slug}.jpg", 1, 1)->get(),
                );

                return $caminho;
            }
        };
    }

    public function test_importa_e_e_idempotente(): void
    {
        Storage::fake('public');
        $importador = new ImportadorPalestras($this->leitorFake(), $this->baixadorFake());

        // roda 2x
        $importador->importar();
        $resumo = $importador->importar();

        // contagens não duplicam
        $this->assertSame(2, Assunto::count());
        $this->assertSame(2, Palestrante::count());
        $this->assertSame(1, Palestra::count());

        $palestra = Palestra::first();
        $this->assertCount(1, $palestra->palestrantesAtivos);          // Ana (ativa)
        $this->assertSame('bruno', $palestra->diretor->slug);          // Bruno (diretor)
        $this->assertSame(['fe'], $palestra->assuntos->pluck('slug')->all());
        $this->assertCount(1, $palestra->destaques);
        $this->assertSame('Fé', $palestra->destaques->first()->destaque);
        $this->assertSame('espiritismo', Assunto::where('slug', 'fe')->first()->parent->slug);
        $this->assertSame('publicado', $palestra->status);
        $this->assertSame('2026-06-28 16:00:00', $palestra->data_da_palestra->format('Y-m-d H:i:s'));
        $this->assertSame(['assuntos' => 2, 'palestrantes' => 2, 'palestras' => 1, 'avisos' => []], $resumo);

        // Ana tem foto_url → foto deve estar na Media Library; Bruno não tem foto_url → sem mídia.
        $ana = Palestrante::where('slug', 'ana')->first();
        $this->assertTrue($ana->fresh()->hasMedia(\App\Models\Palestrante::COLECAO_FOTO));

        $bruno = Palestrante::where('slug', 'bruno')->first();
        $this->assertFalse($bruno->fresh()->hasMedia(\App\Models\Palestrante::COLECAO_FOTO));
    }
}
