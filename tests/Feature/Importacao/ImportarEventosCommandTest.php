<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorEventos;
use App\Models\Departamento;
use App\Models\Evento;
use Database\Seeders\CategoriaEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarEventosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        $this->seed(CategoriaEventoSeeder::class);
        Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        // fake do leitor no container (sem tocar o legado; sem imagens p/ manter determinístico)
        $this->app->bind(LeitorEventos::class, fn () => new class implements LeitorEventos
        {
            public function eventos(): array
            {
                return [[
                    'wp_id' => 1, 'titulo' => 'Feirão de Livros', 'slug' => 'feirao-de-livros',
                    'resumo' => null, 'conteudo' => null, 'data_do_evento' => '1782549000',
                    'evento_publico' => 'true', 'mostrar_horario' => 'true', 'mostrar_horario_definido' => true,
                    'local' => 'CEMA', 'flyer_url' => null, 'galeria_urls' => [], 'departamentos_siglas' => ['DEPRO'],
                ]];
            }
        });

        $this->artisan('cema:importar-eventos')
            ->assertSuccessful();

        $this->assertSame(1, Evento::count());
        $this->assertSame('feirao', Evento::firstWhere('slug', 'feirao-de-livros')->categoria->slug);
    }

    public function test_aborta_sem_categorias_cadastradas(): void
    {
        // NÃO semeia categorias; leitor fake pula a guarda de túnel → dispara o autodiagnóstico.
        $this->app->bind(LeitorEventos::class, fn () => new class implements LeitorEventos
        {
            public function eventos(): array
            {
                return [];
            }
        });

        $this->artisan('cema:importar-eventos')->assertFailed();

        $this->assertSame(0, Evento::count());
    }
}
