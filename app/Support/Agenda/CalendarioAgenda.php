<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Support\Agenda;

use Carbon\Carbon;

class CalendarioAgenda
{
    /**
     * Monta a grade mensal (semana iniciando no domingo) para o SSR do calendário.
     *
     * @param  array<int, string>  $datasComConteudo  set de 'Y-m-d' dos dias publicados no mês
     * @return array{diasVazios:int, dias:list<array{dia:int, ymd:string, temConteudo:bool, hoje:bool, selecionado:bool}>}
     */
    public static function matriz(int $ano, int $mes, Carbon $hoje, ?string $selecionada, array $datasComConteudo): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $diasVazios = $primeiro->dayOfWeek; // 0=domingo … 6=sábado

        $comConteudo = array_flip($datasComConteudo); // lookup O(1) por 'Y-m-d'
        $ehMesCorrente = (int) $hoje->year === $ano && (int) $hoje->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $ymd = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
            $dias[] = [
                'dia' => $d,
                'ymd' => $ymd,
                'temConteudo' => isset($comConteudo[$ymd]),
                'hoje' => $ehMesCorrente && (int) $hoje->day === $d,
                'selecionado' => $selecionada === $ymd,
            ];
        }

        return ['diasVazios' => $diasVazios, 'dias' => $dias];
    }
}
