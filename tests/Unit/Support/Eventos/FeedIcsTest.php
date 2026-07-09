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

    public function test_vevento_carimba_dtstamp_e_sequence_do_updated_at(): void
    {
        $e = $this->ev([]);
        $ics = FeedIcs::documento([$e]);

        // DTSTAMP/SEQUENCE vêm de updated_at (determinístico), não de now().
        $this->assertStringContainsString('DTSTAMP:'.$e->updated_at->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('SEQUENCE:'.$e->updated_at->getTimestamp(), $ics);
    }

    public function test_dobra_summary_longo_com_acentos_sem_partir_utf8(): void
    {
        // Título com vários multibyte e >75 octetos com o prefixo 'SUMMARY:' → precisa dobrar.
        $titulo = 'Reunião de Confraternização e Reflexão sobre a Caridade Espírita Planaltinense';
        $ics = FeedIcs::documento([$this->ev(['titulo' => $titulo])]);

        // (0) houve dobra de fato (continuação CRLF + espaço).
        $this->assertStringContainsString("\r\n ", $ics);
        // (1) nenhuma linha física excede 75 octetos.
        foreach (explode("\r\n", $ics) as $fisica) {
            $this->assertLessThanOrEqual(75, strlen($fisica), "Linha física excede 75 octetos: {$fisica}");
        }
        // (2) desdobrar (remover CRLF+espaço) reconstrói o SUMMARY íntegro — nenhum acento partido.
        $this->assertStringContainsString('SUMMARY:'.$titulo, str_replace("\r\n ", '', $ics));
    }
}
