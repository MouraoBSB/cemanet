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
     * Stub da página de Calendário: lista as próximas palestras publicadas.
     * A fatia do módulo Calendário substitui o corpo por <livewire:palestras.calendario />.
     */
    public function index(): View
    {
        $proximas = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->orderBy('data_da_palestra')
            ->get();

        return view('pages.calendario', ['proximas' => $proximas]);
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
