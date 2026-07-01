<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

use App\Models\Palestra;
use App\Support\Palestras\FeedIcs;
use Illuminate\Database\Eloquent\Builder;

class PalestraController extends Controller
{
    public function index()
    {
        // Só destaca uma palestra realmente FUTURA; sem futura, $proxima é null e o banner some (sem fallback).
        $proxima = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->first();

        return view('palestras.index', compact('proxima'));
    }

    public function show(string $slug)
    {
        $palestra = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos', 'destaques'])
            ->where('slug', $slug)
            ->firstOrFail();

        $base = Palestra::query()->publicado();

        if ($palestra->data_da_palestra === null) {
            $anterior = (clone $base)
                ->where('id', '<', $palestra->id)
                ->orderByDesc('id')
                ->first();

            $proxima = (clone $base)
                ->where('id', '>', $palestra->id)
                ->orderBy('id')
                ->first();
        } else {
            $anterior = (clone $base)
                ->whereNotNull('data_da_palestra')
                ->where('data_da_palestra', '<', $palestra->data_da_palestra)
                ->orderByDesc('data_da_palestra')
                ->first();

            $proxima = (clone $base)
                ->whereNotNull('data_da_palestra')
                ->where('data_da_palestra', '>', $palestra->data_da_palestra)
                ->orderBy('data_da_palestra')
                ->first();
        }

        $assuntoIds = $palestra->assuntos->pluck('id');

        $relacionadas = Palestra::query()
            ->publicado()
            ->where('id', '!=', $palestra->id)
            ->when(
                $assuntoIds->isNotEmpty(),
                fn (Builder $q) => $q->whereHas('assuntos', fn (Builder $a) => $a->whereIn('assuntos.id', $assuntoIds))
            )
            ->with('palestrantesAtivos')
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->take(3)
            ->get();

        if ($relacionadas->count() < 3) {
            $exclui = $relacionadas->pluck('id')->push($palestra->id)->all();
            $relacionadas = $relacionadas->concat(
                Palestra::query()->publicado()
                    ->whereNotIn('id', $exclui)
                    ->with('palestrantesAtivos')
                    ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
                    ->take(3 - $relacionadas->count())
                    ->get()
            );
        }

        return view('palestras.show', compact('palestra', 'anterior', 'proxima', 'relacionadas'));
    }

    public function calendario(string $slug)
    {
        $palestra = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->where('slug', $slug)
            ->firstOrFail();
        abort_if($palestra->data_da_palestra === null, 404);

        return response(FeedIcs::documento([$palestra]), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="palestra-'.$palestra->slug.'.ics"',
        ]);
    }
}
