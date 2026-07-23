<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use App\Support\AutoresEspirituais\ResumoAutor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AutorEspiritualController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        // O5a: autor ativo SEM nenhuma mensagem VISÍVEL a este usuário some da grade (o perfil
        // dele segue 200 por URL direta). Eager-load de 'mensagens' (mesmo escopo viewer-aware)
        // evita N+1 nos pontinhos de formato do card.
        $autores = AutorEspiritual::query()
            ->ativo()
            ->whereHas('mensagens', fn (Builder $q) => $q->publicado()->visiveisPara($usuario))
            ->withCount(['mensagens as mensagens_visiveis_count' => fn (Builder $q) => $q->publicado()->visiveisPara($usuario)])
            // Eager-load recebe a própria relação (BelongsToMany), não um Builder — fechamento SEM tipo.
            ->with(['mensagens' => fn ($q) => $q->publicado()->visiveisPara($usuario)])
            ->orderBy('nome')
            ->get();

        $totalMensagensVisiveis = Mensagem::publicado()->visiveisPara($usuario)->count();
        $destaque = $autores->sortByDesc('mensagens_visiveis_count')->first();   // O3 (desempate por nome via orderBy prévio)

        $resposta = response()->view('autores.index', [
            'autores' => $autores,
            'totalAutores' => $autores->count(),
            'totalMensagensVisiveis' => $totalMensagensVisiveis,
            'destaque' => $destaque,
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store'); // varia por usuário — não cacheável por proxy
        }

        return $resposta;
    }

    public function show(Request $request, string $slug): Response
    {
        $usuario = $request->user();

        // O5a: 404 só para autor inativo/inexistente. Autor ativo sem nenhuma mensagem VISÍVEL
        // segue acessível por URL direta — 200, grade vazia, stats zerados.
        $autor = AutorEspiritual::query()->ativo()->where('slug', $slug)->firstOrFail();

        // As mensagens que ESTE usuário pode ver (anônimo = só públicas ≡ 2B); ordem "recentes"
        // (data desc, nulos por último) em PHP (portável). with('autores'): o card variante=perfil
        // renderiza iniciais/nomes dos autores (evita N+1).
        $mensagens = $autor->mensagens()->publicado()->visiveisPara($usuario)->with(['media', 'autores'])->get()
            ->sortByDesc(fn (Mensagem $m) => $m->data_recebimento?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoAutor($mensagens);

        // Payload enxuto para o Alpine (filtro por formato + ordenação client-side).
        $itensFiltro = $mensagens->map(fn (Mensagem $m) => [
            'id' => $m->id,
            'titulo' => $m->titulo,
            'ts' => $m->data_recebimento?->getTimestamp(),
            'formato' => $m->formato?->value,
        ])->values();

        $resposta = response()->view('autores.show', [
            'autor' => $autor,
            'mensagens' => $mensagens,
            'resumo' => $resumo,
            'destaque' => $mensagens->first(),   // mais recente visível (ou null)
            'itensFiltro' => $itensFiltro,
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store');
        }

        return $resposta;
    }
}
