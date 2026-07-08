<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Support\Eventos;

use Illuminate\Support\Carbon;

/**
 * Regras de período de um evento (data/hora início–fim), em classe pura e testável.
 * Datas comparadas como string Y-m-d (portável); horas como string HH:MM zero-padded.
 * Fonte única de validação: usada no admin (EventoResource) e na importação.
 */
class PeriodoEvento
{
    public static function horaValida(string $hora): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora);
    }

    /** True se, no mesmo dia, a hora de término não é posterior à de início. */
    public static function horaFimAntesNoMesmoDia(?string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): bool
    {
        $mesmoDia = ($dataFim === null || $dataFim === '' || $dataFim === $dataInicio);

        return $mesmoDia
            && $horaInicio !== null && $horaInicio !== '' && self::horaValida($horaInicio)
            && $horaFim !== null && $horaFim !== '' && self::horaValida($horaFim)
            && $horaFim <= $horaInicio;
    }

    /** Mensagens de erro de validação (vazio = válido). */
    public static function erros(?string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): array
    {
        $erros = [];

        if ($dataInicio === null || $dataInicio === '') {
            $erros[] = 'A data de início é obrigatória.';

            return $erros; // sem início, nada a comparar
        }

        foreach (['hora de início' => $horaInicio, 'hora de término' => $horaFim] as $rotulo => $hora) {
            if ($hora !== null && $hora !== '' && ! self::horaValida($hora)) {
                $erros[] = "A {$rotulo} deve estar no formato HH:MM (00:00–23:59).";
            }
        }

        if (($horaFim !== null && $horaFim !== '') && ($horaInicio === null || $horaInicio === '')) {
            $erros[] = 'A hora de término foi informada sem a hora de início. Informe a hora de início ou deixe ambas em branco (dia inteiro).';
        }

        if ($dataFim !== null && $dataFim !== '' && $dataFim < $dataInicio) {
            $erros[] = 'A data de término não pode ser anterior à data de início.';
        }

        if (self::horaFimAntesNoMesmoDia($dataInicio, $horaInicio, $dataFim, $horaFim)) {
            $erros[] = 'No mesmo dia, a hora de término deve ser posterior à de início.';
        }

        return $erros;
    }

    /** Período por extenso em pt-BR (ex.: "27 de junho de 2026 · 8h30 – 12h"). */
    public static function formata(string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): string
    {
        $inicio = Carbon::parse($dataInicio);
        $fim = ($dataFim !== null && $dataFim !== '') ? Carbon::parse($dataFim) : $inicio;

        if ($inicio->isSameDay($fim)) {
            $data = self::dataExtenso($inicio);
            $faixa = self::faixaHoraria($horaInicio, $horaFim);

            return $faixa !== '' ? "{$data} · {$faixa}" : $data;
        }

        return self::intervaloDatas($inicio, $fim);
    }

    private static function dataExtenso(Carbon $d): string
    {
        return $d->translatedFormat('j \d\e F \d\e Y');
    }

    private static function faixaHoraria(?string $horaInicio, ?string $horaFim): string
    {
        if ($horaInicio === null || $horaInicio === '') {
            return '';
        }

        $inicio = self::horaBr($horaInicio);

        return ($horaFim !== null && $horaFim !== '')
            ? "{$inicio} – ".self::horaBr($horaFim)
            : $inicio;
    }

    /** "08:30" → "8h30"; "12:00" → "12h". */
    private static function horaBr(string $hora): string
    {
        [$h, $m] = explode(':', $hora);
        $h = (int) $h;

        return $m === '00' ? "{$h}h" : "{$h}h{$m}";
    }

    private static function intervaloDatas(Carbon $i, Carbon $f): string
    {
        if ($i->year !== $f->year) {
            return $i->translatedFormat('j \d\e F \d\e Y').' a '.$f->translatedFormat('j \d\e F \d\e Y');
        }

        if ($i->month !== $f->month) {
            return $i->translatedFormat('j \d\e F').' a '.$f->translatedFormat('j \d\e F \d\e Y');
        }

        return $i->translatedFormat('j').' a '.$f->translatedFormat('j \d\e F \d\e Y');
    }
}
