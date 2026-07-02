<?php

namespace Tests\Unit\Support\Agenda;

use App\Support\Agenda\CalendarioAgenda;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_offset_do_primeiro_dia_do_mes(): void
    {
        // 01/07/2026 é uma quarta-feira → offset 3 (domingo=0).
        $matriz = CalendarioAgenda::matriz(2026, 7, Carbon::create(2026, 7, 15), null, []);

        $this->assertSame(3, $matriz['diasVazios']);
        $this->assertCount(31, $matriz['dias']);
    }

    public function test_offset_zero_quando_mes_comeca_no_domingo(): void
    {
        // 01/02/2026 é domingo → offset 0.
        $matriz = CalendarioAgenda::matriz(2026, 2, Carbon::create(2026, 2, 10), null, []);

        $this->assertSame(0, $matriz['diasVazios']);
        $this->assertCount(28, $matriz['dias']);
    }

    public function test_ymd_e_estrutura_de_cada_celula(): void
    {
        $matriz = CalendarioAgenda::matriz(2026, 7, Carbon::create(2026, 7, 15), null, []);
        $primeiro = $matriz['dias'][0];

        $this->assertSame(1, $primeiro['dia']);
        $this->assertSame('2026-07-01', $primeiro['ymd']);
        $this->assertArrayHasKey('temConteudo', $primeiro);
        $this->assertArrayHasKey('hoje', $primeiro);
        $this->assertArrayHasKey('selecionado', $primeiro);
    }

    public function test_marca_hoje_selecionado_e_conteudo(): void
    {
        $matriz = CalendarioAgenda::matriz(
            2026, 7,
            Carbon::create(2026, 7, 15),
            '2026-07-10',
            ['2026-07-10', '2026-07-20'],
        );

        $dias = collect($matriz['dias'])->keyBy('dia');

        $this->assertTrue($dias[10]['temConteudo']);
        $this->assertTrue($dias[10]['selecionado']);
        $this->assertFalse($dias[10]['hoje']);

        $this->assertTrue($dias[15]['hoje']);
        $this->assertFalse($dias[15]['selecionado']);
        $this->assertFalse($dias[15]['temConteudo']);

        $this->assertTrue($dias[20]['temConteudo']);
        $this->assertFalse($dias[11]['temConteudo']);
    }

    public function test_nao_marca_hoje_quando_o_mes_exibido_nao_e_o_corrente(): void
    {
        // Exibindo agosto/2026, mas "hoje" é 15/07/2026 → nenhuma célula é hoje.
        $matriz = CalendarioAgenda::matriz(2026, 8, Carbon::create(2026, 7, 15), null, []);

        $this->assertEmpty(collect($matriz['dias'])->firstWhere('hoje', true));
    }
}
