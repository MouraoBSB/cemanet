<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

use App\Models\Palestrante;
use Illuminate\Contracts\View\View;

class PalestranteController extends Controller
{
    public function index(): View
    {
        return view('palestrantes.index');
    }

    public function show(string $slug): View
    {
        $palestrante = Palestrante::query()
            ->ativo()
            ->where('slug', $slug)
            ->firstOrFail();

        $palestras = $palestrante->palestrasMinistradas()
            ->publicado()
            ->with('palestrantesAtivos')
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->get();

        return view('palestrantes.show', compact('palestrante', 'palestras'));
    }
}
