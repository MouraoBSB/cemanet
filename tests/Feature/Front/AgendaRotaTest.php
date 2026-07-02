<?php

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaRotaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_url_nua_resolve_para_a_rota_esperada(): void
    {
        $this->assertSame(url('/agenda-reforma-intima'), route('agenda.index'));
    }

    public function test_url_nua_responde_200(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão de hoje</p>']);

        $this->get('/agenda-reforma-intima')->assertOk();
    }

    public function test_show_exibe_o_dia_com_a_reflexao_no_html(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Semeai a paz no coração</p>']);

        $this->get('/agenda-reforma-intima/2026-07-10')
            ->assertOk()
            ->assertSee('Semeai a paz no coração');
    }

    public function test_dia_futuro_com_conteudo_e_legivel(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-12-25', 'reflexao' => '<p>Natal de luz</p>']);

        $this->get('/agenda-reforma-intima/2026-12-25')
            ->assertOk()
            ->assertSee('Natal de luz');
    }

    public function test_data_valida_sem_conteudo_responde_200(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));

        $this->get('/agenda-reforma-intima/2026-07-11')->assertOk();
    }

    public function test_data_invalida_retorna_404(): void
    {
        $this->get('/agenda-reforma-intima/2026-13-45')->assertNotFound();
    }

    public function test_url_antiga_do_arquivo_redireciona_301(): void
    {
        $this->get('/agenda-reforma')->assertStatus(301)->assertRedirect('/agenda-reforma-intima');
    }

    public function test_slug_legado_numerico_redireciona_301(): void
    {
        AgendaSlugLegado::factory()->create(['slug' => '27057', 'data' => '2026-05-10']);

        $this->get('/agenda-reforma/27057')
            ->assertStatus(301)
            ->assertRedirect('/agenda-reforma-intima/2026-05-10');
    }

    public function test_slug_legado_de_data_redireciona_301(): void
    {
        AgendaSlugLegado::factory()->create(['slug' => '02-de-julho-de-2026', 'data' => '2026-07-02']);

        $this->get('/agenda-reforma/02-de-julho-de-2026')
            ->assertStatus(301)
            ->assertRedirect('/agenda-reforma-intima/2026-07-02');
    }

    public function test_slug_legado_inexistente_retorna_404(): void
    {
        $this->get('/agenda-reforma/nao-existe')->assertNotFound();
    }
}
