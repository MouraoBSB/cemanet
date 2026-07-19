<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Support\AutoresEspirituais\ResumoAutor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class AutorEspiritualController extends Controller
{
    public function index(): View
    {
        // O5a: autor ativo SEM nenhuma pública some da grade (o perfil dele segue 200 por URL direta — Task 6).
        // Eager-load de 'mensagens' (já filtrado por publica()) evita N+1 nos pontinhos de formato do card.
        $autores = AutorEspiritual::query()
            ->ativo()
            ->whereHas('mensagens', fn (Builder $q) => $q->publica())
            ->withCount(['mensagens as mensagens_publicas_count' => fn (Builder $q) => $q->publica()])
            // Eager-load recebe a própria relação (BelongsToMany), não um Builder — fechamento não-tipado.
            ->with(['mensagens' => fn ($q) => $q->publica()])
            ->orderBy('nome')
            ->get();

        $totalMensagensPublicas = Mensagem::publica()->count();
        $destaque = $autores->sortByDesc('mensagens_publicas_count')->first();   // O3 (desempate por nome via orderBy prévio)

        return view('autores.index', [
            'autores' => $autores,
            'totalAutores' => $autores->count(),
            'totalMensagensPublicas' => $totalMensagensPublicas,
            'destaque' => $destaque,
        ]);
    }

    public function show(string $slug): View
    {
        // O5a: 404 só para autor inativo/inexistente. Autor ativo SEM nenhuma pública
        // segue acessível por URL direta — 200, grade vazia, stats zerados.
        $autor = AutorEspiritual::query()->ativo()->where('slug', $slug)->firstOrFail();

        // Só as PÚBLICAS; ordem "recentes" (data desc, nulos por último) em PHP (portável).
        $publicas = $autor->mensagens()->publica()->with('media')->get()
            ->sortByDesc(fn (Mensagem $m) => $m->data_recebimento?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoAutor($publicas);

        // Payload enxuto para o Alpine (filtro por formato + ordenação client-side).
        $itensFiltro = $publicas->map(fn (Mensagem $m) => [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'ts' => $m->data_recebimento?->getTimestamp(),
            'formato' => $m->formato?->value,
        ])->values();

        return view('autores.show', [
            'autor' => $autor,
            'mensagens' => $publicas,
            'resumo' => $resumo,
            'destaque' => $publicas->first(),   // mais recente pública (ou null)
            'itensFiltro' => $itensFiltro,
        ]);
    }
}
