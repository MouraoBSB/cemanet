<?php

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaCrawlavelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dia_com_conteudo_vira_link_e_dia_sem_conteudo_fica_inerte(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão do dia</p>']);

        $resp = $this->get('/agenda-reforma-intima/2026-07-10')->assertOk();

        // Célula com conteúdo = âncora crawlável para a URL datada, com wire:navigate.
        $resp->assertSee('/agenda-reforma-intima/2026-07-10', false);
        $resp->assertSee('wire:navigate', false);

        // Dia 11 (sem conteúdo) NÃO gera link (é <span> inerte).
        $resp->assertDontSee('/agenda-reforma-intima/2026-07-11', false);
    }

    public function test_link_da_agenda_presente_no_header(): void
    {
        $this->get('/')->assertSee('href="'.route('agenda.index').'"', false);
    }
}
