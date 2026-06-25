<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

use App\Models\Palestra;

class PalestraController extends Controller
{
    public function index()
    {
        return view('palestras.index');
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

        return view('palestras.show', compact('palestra', 'anterior', 'proxima'));
    }
}
