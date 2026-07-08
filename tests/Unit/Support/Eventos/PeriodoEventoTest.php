<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Support\Eventos;

use App\Support\Eventos\PeriodoEvento;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PeriodoEventoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setLocale('pt_BR');
    }

    public function test_hora_valida(): void
    {
        $this->assertTrue(PeriodoEvento::horaValida('08:30'));
        $this->assertTrue(PeriodoEvento::horaValida('23:59'));
        $this->assertFalse(PeriodoEvento::horaValida('8:30'));   // sem zero à esquerda
        $this->assertFalse(PeriodoEvento::horaValida('25:00'));  // hora inválida
        $this->assertFalse(PeriodoEvento::horaValida('12:60'));  // minuto inválido
    }

    public function test_erros_exige_data_inicio(): void
    {
        $this->assertNotEmpty(PeriodoEvento::erros(null, null, null, null));
        $this->assertSame([], PeriodoEvento::erros('2026-06-27', null, null, null));
    }

    public function test_erros_data_fim_anterior(): void
    {
        $erros = PeriodoEvento::erros('2026-06-27', null, '2026-06-25', null);
        $this->assertContains('A data de término não pode ser anterior à data de início.', $erros);
    }

    public function test_erros_hora_fim_antes_no_mesmo_dia(): void
    {
        $erros = PeriodoEvento::erros('2026-06-27', '10:00', '2026-06-27', '09:00');
        $this->assertContains('No mesmo dia, a hora de término deve ser posterior à de início.', $erros);
    }

    public function test_hora_fim_antes_no_mesmo_dia_helper(): void
    {
        $this->assertTrue(PeriodoEvento::horaFimAntesNoMesmoDia('2026-06-27', '10:00', null, '09:00'));
        $this->assertTrue(PeriodoEvento::horaFimAntesNoMesmoDia('2026-06-27', '10:00', '2026-06-27', '09:00'));
        $this->assertFalse(PeriodoEvento::horaFimAntesNoMesmoDia('2026-06-27', '10:00', '2026-06-28', '09:00')); // dias diferentes
        $this->assertFalse(PeriodoEvento::horaFimAntesNoMesmoDia('2026-06-27', '08:00', null, '12:00'));
    }

    public function test_erros_hora_formato_invalido(): void
    {
        $this->assertNotEmpty(PeriodoEvento::erros('2026-06-27', '8:30', null, null));
    }

    public function test_erros_hora_fim_sem_hora_inicio(): void
    {
        $this->assertNotEmpty(PeriodoEvento::erros('2026-06-27', null, null, '09:00'));
        $this->assertSame([], PeriodoEvento::erros('2026-06-27', '08:00', null, '09:00')); // com início, ok
    }

    public function test_formata_dia_unico_com_hora(): void
    {
        $this->assertSame('27 de junho de 2026 · 8h30 – 12h',
            PeriodoEvento::formata('2026-06-27', '08:30', null, '12:00'));
    }

    public function test_formata_dia_unico_sem_hora_e_dia_inteiro(): void
    {
        $this->assertSame('27 de junho de 2026',
            PeriodoEvento::formata('2026-06-27', null, '2026-06-27', null));
    }

    public function test_formata_multi_dia_mesmo_mes(): void
    {
        $this->assertSame('27 a 29 de junho de 2026',
            PeriodoEvento::formata('2026-06-27', null, '2026-06-29', null));
    }

    public function test_formata_multi_dia_meses_diferentes(): void
    {
        $this->assertSame('30 de junho a 2 de julho de 2026',
            PeriodoEvento::formata('2026-06-30', null, '2026-07-02', null));
    }
}
