<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituaisMysql;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarAutoresEspirituaisCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        Storage::fake('public');

        // fake do leitor no container (sem tocar o legado; sem imagens p/ determinismo)
        $this->app->bind(LeitorAutoresEspirituais::class, fn () => new class implements LeitorAutoresEspirituais
        {
            public function autores(): array
            {
                return [[
                    'slug' => 'catarina', 'nome' => 'Catarina', 'bio' => '<p>Guia.</p>', 'foto_url' => null,
                ]];
            }
        });

        $this->artisan('cema:importar-autores-espirituais')->assertSuccessful();

        $this->assertSame(1, AutorEspiritual::count());
        $this->assertSame('Catarina', AutorEspiritual::firstWhere('slug', 'catarina')->nome);
    }

    /** Guarda C7/I12: sem bind manual, resolver a INTERFACE devolve o ...Mysql (bind do AppServiceProvider). */
    public function test_interface_do_leitor_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(
            LeitorAutoresEspirituaisMysql::class,
            app(LeitorAutoresEspirituais::class),
        );
    }

    /** Guarda C7/I12: o Importador resolve pelo container (constrói a cadeia real) sem bind manual. */
    public function test_importador_resolve_pelo_container(): void
    {
        $this->assertInstanceOf(
            ImportadorAutoresEspirituais::class,
            app(ImportadorAutoresEspirituais::class),
        );
    }
}
