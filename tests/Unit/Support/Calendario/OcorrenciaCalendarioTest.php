<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Unit\Support\Calendario;

use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OcorrenciaCalendarioTest extends TestCase
{
    private function oc(array $o = []): OcorrenciaCalendario
    {
        return new OcorrenciaCalendario(
            tipo: $o['tipo'] ?? 'evento',
            chave: $o['chave'] ?? 'evento-1',
            titulo: 'X',
            url: '/x',
            inicio: $o['inicio'],
            fim: $o['fim'] ?? null,
            temHora: $o['temHora'] ?? false,
            subtitulo: null,
            corAcento: '#89AB98',
            selo: ['rotulo' => 'S', 'cor' => '#000', 'cor_texto' => '#fff'],
            seloVisibilidade: $o['seloVisibilidade'] ?? null,
        );
    }

    public function test_dias_no_mes_instantaneo_acende_um_dia(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-27 19:00')]);
        $this->assertSame([27], $oc->diasNoMes(2026, 6));
    }

    public function test_dias_no_mes_multidia_acende_todos(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-27'), 'fim' => Carbon::parse('2026-06-29')]);
        $this->assertSame([27, 28, 29], $oc->diasNoMes(2026, 6));
    }

    public function test_dias_no_mes_recorta_na_virada_de_mes(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-30'), 'fim' => Carbon::parse('2026-07-02')]);
        $this->assertSame([30], $oc->diasNoMes(2026, 6));
        $this->assertSame([1, 2], $oc->diasNoMes(2026, 7));
    }

    public function test_ordenar_por_inicio_com_empate_palestra_antes(): void
    {
        $inst = Carbon::parse('2026-06-27 19:00');
        $evento = $this->oc(['tipo' => 'evento', 'chave' => 'evento-1', 'inicio' => $inst]);
        $palestra = $this->oc(['tipo' => 'palestra', 'chave' => 'palestra-1', 'inicio' => $inst]);
        $depois = $this->oc(['tipo' => 'evento', 'chave' => 'evento-2', 'inicio' => Carbon::parse('2026-06-28 10:00')]);

        $ordenado = OcorrenciaCalendario::ordenar(new Collection([$depois, $evento, $palestra]));

        $this->assertSame(['palestra-1', 'evento-1', 'evento-2'], $ordenado->pluck('chave')->all());
    }
}
