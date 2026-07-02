<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorAgenda;
use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarAgendaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_usando_o_leitor_injetado(): void
    {
        // injeta um leitor fake no container (evita depender do legado vivo)
        $this->app->bind(LeitorAgenda::class, fn () => new class implements LeitorAgenda
        {
            public function entradas(): array
            {
                return [[
                    'data' => '2026-06-15', 'wp_id' => 28000, 'post_name' => '15-de-junho-de-2026',
                    'reflexao' => '<p>Reflexão de junho</p>',
                    'mes_titulo' => 'Combater o egoísmo: indiferença e ingratidão',
                    'mes_texto' => '<p>Citação</p>',
                    'meta_dia_titulo' => 'Vencer a indiferença',
                    'meta_dia_texto' => '<p>Meta</p>',
                    'prece' => '<p>Prece</p>',
                    'avisos' => [],
                ]];
            }
        });

        $this->artisan('cema:importar-agenda')
            ->expectsOutputToContain('Importação concluída')
            ->assertExitCode(0);

        $this->assertSame(1, AgendaDia::count());
        $this->assertSame(1, AgendaMetaMes::count());
        $this->assertSame(1, AgendaSlugLegado::count());
        $this->assertSame('2026-06-15', AgendaDia::first()->data->format('Y-m-d'));
        $this->assertSame(
            'Combater o egoísmo: indiferença e ingratidão',
            AgendaMetaMes::where('ano', 2026)->where('mes', 6)->value('titulo'),
        );
    }
}
