<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Enums;

/**
 * Onde mora a verdade do escopo de um tipo de conteúdo: no TIPO (deptos fixos, configurados) ou
 * em CADA REGISTRO (o pivô departamento_<x> do objeto). Não confundir com "como" o escopo chega
 * lá (auto-atribuição pelo autor não existe e está fora da Camada 1).
 */
enum RegimeAcesso: string
{
    case DoTipo = 'do_tipo';
    case PorRegistro = 'por_registro';

    public function rotulo(): string
    {
        return match ($this) {
            self::DoTipo => 'Departamentos fixos do tipo',
            self::PorRegistro => 'Departamentos definidos em cada registro',
        };
    }

    /** Mapa value => rótulo, para o Select do Filament. */
    public static function opcoes(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
