<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Http\Controllers;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Support\Eventos\StatusEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    /** Arquivo /eventos: hero + "Próximo destaque" (fora da grade) + Livewire da grade. */
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Destaque = próximo evento FUTURO visível mais próximo (independe dos filtros).
        $destaque = Evento::query()
            ->publicado()
            ->visiveisPara($usuario)
            ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [now(StatusEvento::FUSO)->toDateString()])
            ->with(['categoria', 'departamentos'])
            ->orderBy('data_inicio')
            ->first();

        return view('eventos.index', ['destaque' => $destaque]);
    }

    public function show(Request $request, string $slug)
    {
        $usuario = $request->user();

        $evento = Evento::query()->publicado()
            ->with(['categoria', 'departamentos'])
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($evento->podeSerVistoPor($usuario), 404); // 404, não 403 (não vaza existência)

        // Relacionados: mesma categoria, visíveis, futuros primeiro; fallback p/ quaisquer visíveis.
        $rel = Evento::query()->publicado()->visiveisPara($usuario)
            ->where('id', '!=', $evento->id)
            ->when($evento->categoria_evento_id, fn (Builder $q) => $q->where('categoria_evento_id', $evento->categoria_evento_id))
            ->with('categoria')
            ->orderByRaw('COALESCE(data_fim, data_inicio) >= ? DESC', [now(StatusEvento::FUSO)->toDateString()])
            ->orderBy('data_inicio')
            ->take(3)->get();

        if ($rel->count() < 3) {
            $exclui = $rel->pluck('id')->push($evento->id)->all();
            $rel = $rel->concat(
                Evento::query()->publicado()->visiveisPara($usuario)
                    ->whereNotIn('id', $exclui)->with('categoria')
                    ->orderBy('data_inicio')->take(3 - $rel->count())->get()
            );
        }

        $resposta = response()->view('eventos.show', ['evento' => $evento, 'relacionados' => $rel]);

        // Single restrita não pode ficar em cache compartilhado.
        if ($evento->visibilidade !== VisibilidadeEvento::Publico) {
            $resposta->header('Cache-Control', 'private, no-store');
        }

        return $resposta;
    }
}
