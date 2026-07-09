<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoInstantesTest extends TestCase
{
    use RefreshDatabase;

    private function eventoBase(array $overrides = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Evento de teste',
            'slug' => 'evento-de-teste',
            'data_inicio' => '2026-06-27',
            'status' => Evento::STATUS_PUBLICADO,
        ], $overrides));
    }

    public function test_inicio_e_fim_utc_com_hora_inicio_e_hora_fim(): void
    {
        $evento = $this->eventoBase([
            'slug' => 'com-hora-inicio-e-fim',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
        ]);

        // America/Sao_Paulo é UTC-3 (sem horário de verão desde 2019).
        $this->assertSame('20260627T110000Z', $evento->inicioUtc()->format('Ymd\THis\Z'));
        $this->assertSame('20260627T150000Z', $evento->fimUtc()->format('Ymd\THis\Z'));
    }

    public function test_fim_utc_sem_hora_fim_usa_inicio_mais_duas_horas(): void
    {
        $evento = $this->eventoBase([
            'slug' => 'sem-hora-fim',
            'hora_inicio' => '08:00',
        ]);

        $this->assertSame('20260627T110000Z', $evento->inicioUtc()->format('Ymd\THis\Z'));
        $this->assertSame('20260627T130000Z', $evento->fimUtc()->format('Ymd\THis\Z'));
    }

    public function test_inicio_e_fim_utc_dia_inteiro(): void
    {
        $evento = $this->eventoBase([
            'slug' => 'dia-inteiro',
        ]);

        // Sem hora_inicio: início vira 00:00 em America/Sao_Paulo.
        $this->assertSame('20260627T030000Z', $evento->inicioUtc()->format('Ymd\THis\Z'));
        $this->assertSame('20260627T050000Z', $evento->fimUtc()->format('Ymd\THis\Z'));
    }

    public function test_accessor_status_selo_e_eh_passado_para_evento_encerrado(): void
    {
        $evento = $this->eventoBase([
            'slug' => 'evento-encerrado',
            'data_inicio' => '2026-01-10',
            'data_fim' => '2026-01-10',
        ]);

        $this->assertSame('Encerrado', $evento->status_selo['rotulo']);
        $this->assertTrue($evento->eh_passado);
    }

    public function test_accessor_eh_passado_falso_para_evento_futuro(): void
    {
        $evento = $this->eventoBase([
            'slug' => 'evento-futuro',
            'data_inicio' => '2026-12-25',
            'data_fim' => '2026-12-25',
        ]);

        $this->assertFalse($evento->eh_passado);
    }
}
