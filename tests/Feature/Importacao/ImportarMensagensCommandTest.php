<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorMensagens;
use App\Importacao\LeitorMensagens;
use App\Importacao\LeitorMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarMensagensCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        Storage::fake('public');

        // fake do leitor no container (sem tocar o legado; sem imagens p/ determinismo)
        $this->app->bind(LeitorMensagens::class, fn () => new class implements LeitorMensagens
        {
            public function mensagens(): array
            {
                return [[
                    'wp_id' => 1, 'titulo' => 'Paz', 'slug' => 'paz', 'corpo' => '<p>Paz.</p>',
                    'formato' => 'psicografia', 'data_recebimento' => '1722902400', 'nivel' => 'publico',
                    'autores_slugs' => [], 'fotos_urls' => [], 'link_arquivo' => null,
                    'liberar_download' => 'false', 'status' => 'publicado',
                ]];
            }
        });

        $this->artisan('cema:importar-mensagens')->assertSuccessful();

        $this->assertSame(1, Mensagem::count());
        $this->assertSame('Paz', Mensagem::firstWhere('wp_id', 1)->titulo);
    }

    /** Guarda I16: sem bind manual, resolver a INTERFACE devolve o ...Mysql (bind do AppServiceProvider). */
    public function test_interface_do_leitor_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(
            LeitorMensagensMysql::class,
            app(LeitorMensagens::class),
        );
    }

    /** Guarda I16: o Importador resolve pelo container (constrói a cadeia real) sem bind manual. */
    public function test_importador_resolve_pelo_container(): void
    {
        $this->assertInstanceOf(
            ImportadorMensagens::class,
            app(ImportadorMensagens::class),
        );
    }
}
