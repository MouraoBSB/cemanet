<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

use App\Models\Palestra;
use App\Models\Palestrante;
use App\Support\Palestrantes\ResumoPerfil;
use Illuminate\Contracts\View\View;

class PalestranteController extends Controller
{
    public function index(): View
    {
        $proxima = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->first(); // sem fallback (pode ser null)

        return view('palestrantes.index', [
            'totalColaboradores' => Palestrante::ativo()->count(),
            'totalAcervo' => Palestra::publicado()->count(),
            'proxima' => $proxima,
        ]);
    }

    public function show(string $slug): View
    {
        $palestrante = Palestrante::query()
            ->ativo()
            ->where('slug', $slug)
            ->firstOrFail();

        // Publicadas ministradas; ordem "recentes" (data desc, nulos por último) em PHP (portável).
        $palestras = $palestrante->palestrasMinistradas()
            ->publicado()
            ->with(['assuntos', 'palestrantesAtivos'])
            ->get()
            ->sortByDesc(fn (Palestra $p) => $p->data_da_palestra?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoPerfil($palestras);

        $proxima = $palestrante->palestrasMinistradas()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->orderBy('data_da_palestra')
            ->first(); // sem fallback (big-bang)

        // Payload do filtro/ordenação client-side (Alpine): ordenação feita no cliente.
        $itensFiltro = $palestras->map(fn (Palestra $p) => [
            'id' => $p->id,
            'titulo' => $p->titulo,
            'ts' => $p->data_da_palestra?->getTimestamp(),
            'assuntos' => $p->assuntos->pluck('slug')->values()->all(),
        ])->values();

        return view('palestrantes.show', [
            'palestrante' => $palestrante,
            'palestras' => $palestras,
            'resumo' => $resumo,
            'areas' => $resumo->areas(),
            'areasHero' => $resumo->areasHero(),
            'proxima' => $proxima,
            'itensFiltro' => $itensFiltro,
        ]);
    }
}
