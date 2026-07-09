<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Unit\Support\Eventos;

use App\Models\Evento;
use App\Support\Eventos\FeedIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedIcsTest extends TestCase
{
    use RefreshDatabase;

    private function ev(array $o): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'E', 'slug' => 'e', 'data_inicio' => '2026-06-27', 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    public function test_com_hora_usa_datetime_e_hora_fim(): void
    {
        $ics = FeedIcs::documento([$this->ev(['hora_inicio' => '08:00', 'hora_fim' => '12:00'])]);
        $this->assertStringContainsString('DTSTART:20260627T110000Z', $ics); // 08:00 SP = 11:00 UTC
        $this->assertStringContainsString('DTEND:20260627T150000Z', $ics);   // 12:00 SP = 15:00 UTC
    }

    public function test_sem_hora_fim_soma_2h(): void
    {
        $ics = FeedIcs::documento([$this->ev(['hora_inicio' => '08:00'])]);
        $this->assertStringContainsString('DTEND:20260627T130000Z', $ics);   // 08:00+2h = 10:00 SP = 13:00 UTC
    }

    public function test_dia_inteiro_value_date_com_dtend_exclusivo(): void
    {
        $ics = FeedIcs::documento([$this->ev(['data_fim' => '2026-06-29'])]); // sem hora → dia inteiro, 27→29
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260627', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260630', $ics); // data_fim + 1 dia (exclusivo)
    }

    public function test_documento_tem_envelope_vcalendar(): void
    {
        $ics = FeedIcs::documento([$this->ev([])]);
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('PRODID:-//CEMA//Eventos//PT-BR', $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }
}
