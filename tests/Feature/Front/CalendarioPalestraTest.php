<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioPalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_responde_em_utc_com_duracao_padrao(): void
    {
        // 19:00 America/Sao_Paulo => 22:00Z; +1h30 padrão => 23:30Z
        Palestra::factory()->create([
            'slug' => 'cema-65',
            'titulo' => 'CEMA 65 Anos',
            'status' => Palestra::STATUS_PUBLICADO,
            'duracao' => null,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ]);

        $resp = $this->get(route('palestras.calendario', 'cema-65'));

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
        $resp->assertSee('DTSTART:20260621T220000Z', false);
        $resp->assertSee('DTEND:20260621T233000Z', false);
        $resp->assertSee('SUMMARY:CEMA 65 Anos', false);
    }

    public function test_ics_404_sem_data(): void
    {
        Palestra::factory()->create(['slug' => 'sem-data', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => null]);

        $this->get(route('palestras.calendario', 'sem-data'))->assertNotFound();
    }
}
