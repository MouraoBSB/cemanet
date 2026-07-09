<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Enums;

enum VisibilidadeEvento: string
{
    case Publico = 'publico';
    case Logados = 'logados';
    case Trabalhadores = 'trabalhadores';
    case Diretoria = 'diretoria';

    /** Nível mínimo (roles.nivel) exigido para ver o evento; 0 = qualquer visitante. */
    public function nivelMinimo(): int
    {
        return match ($this) {
            self::Publico => 0,
            self::Logados => 10,
            self::Trabalhadores => 20,
            self::Diretoria => 30,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Logados => 'Somente logados',
            self::Trabalhadores => 'Trabalhadores e diretoria',
            self::Diretoria => 'Somente diretoria',
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

    public function cor(): string
    {
        return match ($this) {
            self::Publico => '#89AB98',
            self::Logados => '#6E9FCB',
            self::Trabalhadores => '#E79048',
            self::Diretoria => '#C33A36',
        };
    }
}
