<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Support\AutoresEspirituais;

use App\Enums\FormatoMensagem;
use App\Models\Mensagem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resumo do perfil de um autor espiritual, calculado em PHP (portável SQLite/MySQL)
 * a partir da coleção de mensagens PÚBLICAS atribuídas ao autor: total, última
 * mensagem, distribuição por formato e formato predominante. Sem query interna
 * (recebe a coleção já materializada pelo controller) — 100% testável.
 */
class ResumoAutor
{
    /** Cores dos pontinhos por formato (mesma paleta do card de autor). */
    private const COR = [
        'psicografia' => '#8f83d6',
        'psicofonia' => '#6E9FCB',
        'pictografia' => '#89AB98',
    ];

    /** @param Collection<int, Mensagem> $mensagens */
    public function __construct(private Collection $mensagens) {}

    public function total(): int
    {
        return $this->mensagens->count();
    }

    /** Data da mensagem mais recente; null quando não há data. */
    public function ultimaMensagem(): ?Carbon
    {
        return $this->mensagens
            ->pluck('data_recebimento')
            ->filter()
            ->max();
    }

    /**
     * Distribuição por formato: chave = valor do enum; cada item traz o enum,
     * o rótulo, a contagem e a cor. Ordenado por frequência (desc).
     *
     * @return Collection<string, array{formato: FormatoMensagem, valor: string, rotulo: string, count: int, cor: string}>
     */
    public function porFormato(): Collection
    {
        return $this->mensagens
            ->groupBy(fn (Mensagem $m) => $m->formato->value)
            ->map(function (Collection $grupo, string $valor) {
                $formato = FormatoMensagem::from($valor);

                return [
                    'formato' => $formato,
                    'valor' => $valor,
                    'rotulo' => $formato->rotulo(),
                    'count' => $grupo->count(),
                    'cor' => self::COR[$valor] ?? '#8f83d6',
                ];
            })
            ->sortByDesc('count');
    }

    /** Formato predominante (o mais frequente); null quando não há mensagens. */
    public function predominante(): ?FormatoMensagem
    {
        $primeiraChave = $this->porFormato()->keys()->first();

        return $primeiraChave !== null ? FormatoMensagem::from($primeiraChave) : null;
    }

    /**
     * Formatos distintos das públicas, para os selos do hero (já ordenados por
     * frequência). Reindexado para iteração simples na view.
     *
     * @return Collection<int, array{formato: FormatoMensagem, valor: string, rotulo: string, count: int, cor: string}>
     */
    public function selos(): Collection
    {
        return $this->porFormato()->values();
    }
}
