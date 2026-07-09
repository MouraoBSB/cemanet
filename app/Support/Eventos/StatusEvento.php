<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Support\Eventos;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Selo de status / contagem regressiva de um evento, a partir de data_inicio/data_fim (Y-m-d).
 * Regras do design (§07): Encerrado / Acontecendo agora (só multi-dia) / É hoje / É amanhã /
 * Faltam N dias / Em N dias. Datas comparadas por dia (start-of-day), fuso America/Sao_Paulo.
 */
class StatusEvento
{
    public const FUSO = 'America/Sao_Paulo';

    /** @return array{estado:string,rotulo:string,cor:string,cor_texto:string} */
    public static function para(?string $dataInicio, ?string $dataFim, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ? $hoje->copy() : Carbon::today(self::FUSO))->startOfDay();
        $inicio = Carbon::parse((string) $dataInicio, self::FUSO)->startOfDay();
        $fim = Carbon::parse((string) ($dataFim ?: $dataInicio), self::FUSO)->startOfDay();

        if ($hoje->greaterThan($fim)) {
            return ['estado' => 'passado', 'rotulo' => 'Encerrado', 'cor' => '#2f2952', 'cor_texto' => '#FFFFFF'];
        }

        // Só multi-dia em curso vira "Acontecendo agora" (evento de 1 dia hoje cai em "É hoje").
        if ($fim->greaterThan($inicio) && $hoje->betweenIncluded($inicio, $fim)) {
            return ['estado' => 'acontecendo', 'rotulo' => 'Acontecendo agora', 'cor' => '#C33A36', 'cor_texto' => '#FFFFFF'];
        }

        $dias = (int) $hoje->diffInDays($inicio, false); // início − hoje

        // cor_texto garante contraste WCAG AA: branco nos fundos escuros; tinta #26242E nos claros (#E79048/#89AB98).
        return match (true) {
            $dias <= 0 => ['estado' => 'futuro', 'rotulo' => 'É hoje', 'cor' => '#C33A36', 'cor_texto' => '#FFFFFF'],
            $dias === 1 => ['estado' => 'futuro', 'rotulo' => 'É amanhã', 'cor' => '#E79048', 'cor_texto' => '#26242E'],
            $dias <= 7 => ['estado' => 'futuro', 'rotulo' => "Faltam {$dias} dias", 'cor' => '#E79048', 'cor_texto' => '#26242E'],
            default => ['estado' => 'futuro', 'rotulo' => "Em {$dias} dias", 'cor' => '#89AB98', 'cor_texto' => '#26242E'],
        };
    }
}
