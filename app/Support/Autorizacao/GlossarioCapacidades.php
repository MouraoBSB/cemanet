<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Support\Autorizacao;

/**
 * Fonte única do vocabulário de CAPACIDADE (quem edita). Mesmo padrão declarativo de
 * App\Importacao\GlossarioUsuarios, em local próprio. Palestrante/Biblioteca ficam FORA
 * (decisão pendente do "Meu Perfil").
 */
class GlossarioCapacidades
{
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda'];

    public const ACOES = ['ver', 'criar', 'editar', 'excluir'];

    /** @return list<string> os 16 nomes "recurso.acao". */
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
}
