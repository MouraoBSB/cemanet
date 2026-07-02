<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaAcessibilidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dia_de_hoje_marcado_com_aria_current_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão de hoje</p>']);

        $this->get('/agenda-reforma-intima')
            ->assertOk()
            ->assertSee('aria-current="date"', false);
    }

    public function test_setas_de_dia_e_de_mes_tem_aria_label(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        // Conteúdo em três meses garante setas de mês e de dia (prev/next) ativas.
        AgendaDia::factory()->create(['data' => '2026-06-20', 'reflexao' => '<p>Junho</p>']);
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Julho</p>']);
        AgendaDia::factory()->create(['data' => '2026-08-05', 'reflexao' => '<p>Agosto</p>']);

        $resp = $this->get('/agenda-reforma-intima/2026-07-10')->assertOk();

        $resp->assertSee('aria-label="Mês anterior"', false);
        $resp->assertSee('aria-label="Próximo mês"', false);
        $resp->assertSee('aria-label="Dia anterior com conteúdo"', false);
        $resp->assertSee('aria-label="Próximo dia com conteúdo"', false);
    }

    public function test_estado_vazio_tem_noindex_e_link_para_hoje(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        // Data válida SEM AgendaDia publicada → estado vazio.
        $resp = $this->get('/agenda-reforma-intima/2026-07-11')->assertOk();

        $resp->assertSee('name="robots"', false);
        $resp->assertSee('content="noindex"', false);
        $resp->assertSee('href="'.route('agenda.index').'"', false);
        $resp->assertSee('Voltar para hoje');
    }

    public function test_url_datada_com_conteudo_nao_e_noindex(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Conteúdo real</p>']);

        $this->get('/agenda-reforma-intima/2026-07-10')
            ->assertOk()
            ->assertDontSee('content="noindex"', false);
    }
}
