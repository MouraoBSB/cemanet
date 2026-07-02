<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Http\Controllers;

use App\Models\AgendaDia;
use App\Support\Agenda\CalendarioAgenda;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    /** URL nua = "hoje" de Brasília (evergreen, canônica de si). Habilita o script de fuso. */
    public function index(): View
    {
        return $this->montarPagina(Carbon::today(), ehUrlNua: true);
    }

    /** Página datada. Valida a data de verdade (a regex aceita 2026-13-45) → 404 em vez de 500. */
    public function show(string $data): View
    {
        $dataAtual = rescue(fn () => Carbon::createFromFormat('!Y-m-d', $data), null, false);
        abort_if($dataAtual === null || $dataAtual->format('Y-m-d') !== $data, 404);

        return $this->montarPagina($dataAtual, ehUrlNua: false);
    }

    /** Monta o payload SSR compartilhado por index/show (card do dia + matriz + navegação). */
    private function montarPagina(Carbon $dataAtual, bool $ehUrlNua): View
    {
        $hojeBrasilia = Carbon::today();
        $ymd = $dataAtual->format('Y-m-d');

        $dia = AgendaDia::publicado()->where('data', $ymd)->first();
        $metaMes = $dia?->metaMes();
        $temConteudo = $dia !== null;

        $inicioMes = $dataAtual->copy()->startOfMonth();
        $fimMes = $dataAtual->copy()->endOfMonth();

        $datasComConteudo = AgendaDia::publicado()
            ->whereBetween('data', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->get(['data'])
            ->map(fn (AgendaDia $d) => $d->data->format('Y-m-d'))
            ->all();

        $matriz = CalendarioAgenda::matriz(
            (int) $dataAtual->year,
            (int) $dataAtual->month,
            $hojeBrasilia,
            $ymd,
            $datasComConteudo,
        );

        // Dia anterior/próximo COM conteúdo (navegação de setas do card).
        $diaAnterior = AgendaDia::publicado()->where('data', '<', $ymd)
            ->orderByDesc('data')->first()?->data->format('Y-m-d');
        $diaProximo = AgendaDia::publicado()->where('data', '>', $ymd)
            ->orderBy('data')->first()?->data->format('Y-m-d');

        // Mês anterior/próximo: primeiro dia COM conteúdo de cada.
        $refMesAnt = AgendaDia::publicado()->where('data', '<', $inicioMes->toDateString())
            ->orderByDesc('data')->first();
        $mesAnterior = $refMesAnt
            ? AgendaDia::publicado()
                ->whereYear('data', $refMesAnt->data->year)
                ->whereMonth('data', $refMesAnt->data->month)
                ->orderBy('data')->first()?->data->format('Y-m-d')
            : null;
        // Ordenado asc, o primeiro dia > fimMes já é o 1º dia com conteúdo do próximo mês com agenda.
        $mesProximo = AgendaDia::publicado()->where('data', '>', $fimMes->toDateString())
            ->orderBy('data')->first()?->data->format('Y-m-d');

        return view('agenda.index', [
            'dia' => $dia,
            'metaMes' => $metaMes,
            'matriz' => $matriz,
            'diaAnterior' => $diaAnterior,
            'diaProximo' => $diaProximo,
            'mesAnterior' => $mesAnterior,
            'mesProximo' => $mesProximo,
            'ehUrlNua' => $ehUrlNua,
            'hojeBrasilia' => $hojeBrasilia,
            'dataAtual' => $dataAtual,
            'temConteudo' => $temConteudo,
        ]);
    }
}
