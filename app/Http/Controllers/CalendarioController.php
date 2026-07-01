<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-30

namespace App\Http\Controllers;

use App\Models\Palestra;
use App\Support\Palestras\FeedIcs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarioController extends Controller
{
    /**
     * Página do Calendário de Palestras: casca (hero + breadcrumb + Livewire) e SEO.
     * `$proximasParaSeo` alimenta o JSON-LD ItemList/Event da view.
     */
    public function index(): View
    {
        $proximasParaSeo = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(16)
            ->get();

        return view('palestras.calendario', compact('proximasParaSeo'));
    }

    /**
     * Feed .ics agregado das próximas ≤16 palestras publicadas.
     * Inline por padrão; ?download=1 adiciona Content-Disposition: attachment.
     */
    public function feed(Request $request): Response
    {
        $palestras = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(16)
            ->get();

        $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="cema-palestras.ics"';
        }

        return response(FeedIcs::documento($palestras), 200, $headers);
    }
}
