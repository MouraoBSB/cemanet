<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Palestra;
use App\Support\Palestras\DuracaoPalestra;
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
            ->map(function (Palestra $p) {
                $inicio = $p->data_da_palestra;
                $fim = $inicio->copy()->addMinutes(DuracaoPalestra::minutos($p->duracao));
                $ev = [
                    '@type' => 'Event',
                    'name' => $p->titulo,
                    'startDate' => $inicio->toIso8601String(),
                    'endDate' => $fim->toIso8601String(),
                    'eventAttendanceMode' => $p->online
                        ? 'https://schema.org/OnlineEventAttendanceMode'
                        : 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => $p->online
                        ? ['@type' => 'VirtualLocation', 'url' => $p->link_youtube]
                        : ['@type' => 'Place', 'name' => config('cema.nome'), 'address' => config('cema.endereco')],
                    'url' => route('palestras.show', $p->slug),
                ];
                if ($p->palestrantesAtivos->isNotEmpty()) {
                    $ev['performer'] = $p->palestrantesAtivos->map(fn ($x) => ['@type' => 'Person', 'name' => $x->nome])->all();
                }

                return $ev;
            });

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
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => ['@type' => 'Place', 'name' => config('cema.nome'), 'address' => config('cema.endereco')],
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
