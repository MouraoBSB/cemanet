<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Enums;

enum FormatoMensagem: string
{
    case Psicografia = 'psicografia';
    case Psicofonia = 'psicofonia';
    case Pictografia = 'pictografia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Psicografia => 'Psicografia',
            self::Psicofonia => 'Psicofonia',
            self::Pictografia => 'Pictografia',
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
