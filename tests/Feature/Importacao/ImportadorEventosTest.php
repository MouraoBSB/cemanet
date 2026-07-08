<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Importacao;

use App\Enums\VisibilidadeEvento;
use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorEventos;
use App\Importacao\LeitorEventos;
use App\Models\Departamento;
use App\Models\Evento;
use Database\Seeders\CategoriaEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorEventosTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 PNG válido (evita HTTP/GD real; addMediaFromString aceita). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    private function leitor(array $eventos): LeitorEventos
    {
        return new class($eventos) implements LeitorEventos
        {
            public function __construct(private array $eventos) {}

            public function eventos(): array
            {
                return $this->eventos;
            }
        };
    }

    /** Baixador que devolve bytes fixos (sem HTTP), ou null quando a URL é vazia. */
    private function baixador(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixarCapado(?string $url, int $teto = 2000): ?string
            {
                return $url ? base64_decode(ImportadorEventosTest::pngBytes()) : null;
            }
        };
    }

    public static function pngBytes(): string
    {
        return self::PNG_1X1;
    }

    private function eventoLegado(array $overrides = []): array
    {
        return array_merge([
            'wp_id' => 27457,
            'titulo' => 'Brechó Solidário do CEMA – 27 de junho',
            'slug' => 'brecho-solidario-27-de-junho',
            'resumo' => 'Garimpar, ajudar e reencontrar.',
            'conteudo' => '<p>Venha!</p>',
            'data_do_evento' => (string) Carbon::create(2026, 6, 27, 8, 30, 0, 'UTC')->timestamp, // JetEngine grava o relógio local como se fosse UTC
            'evento_publico' => 'true',
            'mostrar_horario' => 'true',
            'mostrar_horario_definido' => true,
            'local' => 'CEMA',
            'flyer_url' => 'https://legado.example/wp-content/uploads/flyer.jpg',
            'galeria_urls' => ['https://legado.example/g1.jpg', 'https://legado.example/g2.jpg'],
            'departamentos_siglas' => ['DEPRO'],
        ], $overrides);
    }

    private function importar(array $eventos): array
    {
        return (new ImportadorEventos($this->leitor($eventos), $this->baixador()))->importar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); // isola a mídia anexada (flyer/galeria) do disco real
        $this->seed(CategoriaEventoSeeder::class);
        Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções e Eventos', 'slug' => 'depro']);
        Departamento::create(['sigla' => 'DED', 'nome' => 'Estudos Doutrinários', 'slug' => 'ded']);
    }

    public function test_importa_evento_publico_com_mapeamento_completo(): void
    {
        $this->importar([$this->eventoLegado()]);

        $evento = Evento::firstWhere('slug', 'brecho-solidario-27-de-junho');
        $this->assertNotNull($evento);
        $this->assertSame(27457, $evento->wp_id);
        $this->assertSame('Garimpar, ajudar e reencontrar.', $evento->resumo);
        $this->assertSame('2026-06-27', $evento->data_inicio->format('Y-m-d'));
        $this->assertSame('08:30', $evento->hora_inicio); // unixParaData: relógio UTC 08:30 reinterpretado como São Paulo
        $this->assertNull($evento->data_fim);
        $this->assertSame(VisibilidadeEvento::Publico, $evento->visibilidade);
        $this->assertSame('brecho', $evento->categoria->slug);
        $this->assertTrue($evento->departamentos->contains('sigla', 'DEPRO'));
        $this->assertTrue($evento->hasMedia(Evento::COLECAO_FLYER));
        $this->assertSame(2, $evento->getMedia(Evento::COLECAO_GALERIA)->count());
    }

    public function test_nao_publico_vira_diretoria_com_aviso(): void
    {
        $resumo = $this->importar([$this->eventoLegado([
            'slug' => 'reuniao-diretoria', 'titulo' => 'Reunião de Diretoria',
            'evento_publico' => 'false', 'flyer_url' => null, 'galeria_urls' => [], 'departamentos_siglas' => [],
        ])]);

        $evento = Evento::firstWhere('slug', 'reuniao-diretoria');
        $this->assertSame(VisibilidadeEvento::Diretoria, $evento->visibilidade);
        $this->assertNull($evento->categoria_evento_id); // "Reunião de Diretoria" não casa nenhuma categoria
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'diretoria')));
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'categoria não inferida')));
        $this->assertSame(1, $resumo['contadores']['diretoria']);
        $this->assertSame(1, $resumo['contadores']['sem_categoria']);
    }

    public function test_mostrar_horario_off_zera_a_hora(): void
    {
        $this->importar([$this->eventoLegado([
            'slug' => 'sem-hora', 'mostrar_horario' => 'false', 'mostrar_horario_definido' => true,
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $this->assertNull(Evento::firstWhere('slug', 'sem-hora')->hora_inicio);
    }

    public function test_mostrar_horario_ausente_mantem_a_hora(): void
    {
        $this->importar([$this->eventoLegado([
            'slug' => 'com-hora-default', 'mostrar_horario' => null, 'mostrar_horario_definido' => false,
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $this->assertSame('08:30', Evento::firstWhere('slug', 'com-hora-default')->hora_inicio);
    }

    public function test_departamento_desconhecido_gera_aviso(): void
    {
        $resumo = $this->importar([$this->eventoLegado([
            'slug' => 'depto-x', 'departamentos_siglas' => ['DEPRO', 'DECOM'],
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $evento = Evento::firstWhere('slug', 'depto-x');
        $this->assertTrue($evento->departamentos->contains('sigla', 'DEPRO'));
        $this->assertFalse($evento->departamentos->contains('sigla', 'DECOM'));
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'DECOM')));
    }

    public function test_e_idempotente(): void
    {
        $this->importar([$this->eventoLegado()]);
        $this->importar([$this->eventoLegado()]); // 2ª vez

        $this->assertSame(1, Evento::count());
        $evento = Evento::firstWhere('slug', 'brecho-solidario-27-de-junho');
        $this->assertTrue($evento->hasMedia(Evento::COLECAO_FLYER)); // não duplicou
        $this->assertSame(2, $evento->getMedia(Evento::COLECAO_GALERIA)->count());
    }
}
