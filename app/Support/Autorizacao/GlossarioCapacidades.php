<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Support\Autorizacao;

use App\Models\AgendaDia;
use App\Models\AutorEspiritual;
use App\Models\Evento;
use App\Models\Mensagem;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;

/**
 * Fonte única do vocabulário de CAPACIDADE (quem edita). Mesmo padrão declarativo de
 * App\Importacao\GlossarioUsuarios, em local próprio. Biblioteca fica FORA (singleton admin-only).
 */
class GlossarioCapacidades
{
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante', 'autor_espiritual', 'mensagem'];

    public const ACOES = ['ver', 'criar', 'editar', 'excluir'];

    /** Rótulos legíveis dos recursos (slug ≠ model em 'agenda' e 'palestrante'). */
    public const RECURSOS_ROTULOS = [
        'evento' => 'Evento',
        'palestra' => 'Palestra',
        'post' => 'Post',
        'agenda' => 'Agenda do Dia',
        'palestrante' => 'Palestrante',
        'autor_espiritual' => 'Autor Espiritual',
        'mensagem' => 'Mensagem',
    ];

    /** Mapa canônico recurso => model (fonte única). Slug ≠ model em 'agenda' e 'palestrante' — ver :17. */
    public const RECURSOS_MODELS = [
        'evento' => Evento::class,
        'palestra' => Palestra::class,
        'post' => Post::class,
        'agenda' => AgendaDia::class,
        'palestrante' => Palestrante::class,
        'autor_espiritual' => AutorEspiritual::class,
        'mensagem' => Mensagem::class,
    ];

    /** Rótulos legíveis das ações. */
    public const ACOES_ROTULOS = [
        'ver' => 'Ver',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
    ];

    /** @return list<string> os 28 nomes "recurso.acao". */
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

    /** Model do recurso, ou null se o slug não está no catálogo. */
    public static function modelDe(string $recurso): ?string
    {
        return self::RECURSOS_MODELS[$recurso] ?? null;
    }
}
