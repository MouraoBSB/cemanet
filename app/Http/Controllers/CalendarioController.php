<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Palestra;
use App\Support\Palestras\FeedIcs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarioController extends Controller
{
    /**
     * Página do Calendário unificado (Palestras + Eventos): casca (hero + breadcrumb + Livewire) e SEO.
     * `$ocorrenciasSeo` alimenta o JSON-LD ItemList/Event da view — só ocorrências públicas.
     */
    public function index(Request $request): Response
    {
        $palestras = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos'])
            ->orderBy('data_da_palestra')->take(16)->get()
            ->map(fn (Palestra $p) => [
                '@type' => 'Event',
                'name' => $p->titulo,
                'startDate' => $p->data_da_palestra->toIso8601String(),
                'url' => route('palestras.show', $p->slug),
            ]);

        $eventos = Evento::query()->publicado()->visiveisPara(null)
            ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [now('America/Sao_Paulo')->toDateString()])
            ->orderBy('data_inicio')->take(16)->get()
            ->map(function (Evento $e) {
                $intervalo = $e->intervaloSchema();

                return [
                    '@type' => 'Event',
                    'name' => $e->titulo,
                    'startDate' => $intervalo['inicio'],
                    'endDate' => $intervalo['fim'],
                    'url' => route('eventos.show', $e->slug),
                ];
            });

        $ocorrenciasSeo = $palestras->concat($eventos)->sortBy('startDate')->take(16)->values();

        $resposta = response()->view('calendario', ['ocorrenciasSeo' => $ocorrenciasSeo]);

        // Página varia por nível de acesso quando logado → nunca em cache compartilhado.
        if ($request->user() !== null) {
            $resposta->header('Cache-Control', 'private, no-store');
        }

        return $resposta;
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
