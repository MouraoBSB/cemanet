<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
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
        abort(501);   // corpo real na Task 6
    }
}
