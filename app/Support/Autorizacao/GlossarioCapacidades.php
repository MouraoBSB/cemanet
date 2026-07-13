<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Support\Autorizacao;

/**
 * Fonte única do vocabulário de CAPACIDADE (quem edita). Mesmo padrão declarativo de
 * App\Importacao\GlossarioUsuarios, em local próprio. Biblioteca fica FORA (singleton admin-only).
 */
class GlossarioCapacidades
{
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante'];

    public const ACOES = ['ver', 'criar', 'editar', 'excluir'];

    /** Rótulos legíveis dos recursos (slug ≠ model em 'agenda' e 'palestrante'). */
    public const RECURSOS_ROTULOS = [
        'evento' => 'Evento',
        'palestra' => 'Palestra',
        'post' => 'Post',
        'agenda' => 'Agenda do Dia',
        'palestrante' => 'Palestrante',
    ];

    /** Rótulos legíveis das ações. */
    public const ACOES_ROTULOS = [
        'ver' => 'Ver',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
    ];

    /** @return list<string> os 20 nomes "recurso.acao". */
    public static function permissions(): array
    {
        $nomes = [];
        foreach (self::RECURSOS as $recurso) {
            foreach (self::ACOES as $acao) {
                $nomes[] = "{$recurso}.{$acao}";
            }
        }

        return $nomes;
    }

    public static function rotuloRecurso(string $recurso): string
    {
        return self::RECURSOS_ROTULOS[$recurso] ?? ucfirst($recurso);
    }

    public static function rotuloAcao(string $acao): string
    {
        return self::ACOES_ROTULOS[$acao] ?? ucfirst($acao);
    }
}
