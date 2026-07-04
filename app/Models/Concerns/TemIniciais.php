<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Iniciais (1ª letra das 2 primeiras palavras do nome, maiúsculas, fallback '?').
 * Fallback de avatar reutilizável. O model define a fonte do nome via nomeParaIniciais().
 */
trait TemIniciais
{
    protected function iniciais(): Attribute
    {
        return Attribute::get(function (): string {
            $palavras = preg_split('/\s+/', trim($this->nomeParaIniciais()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $letras = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palavras, 0, 2));

            return $letras === [] ? '?' : implode('', $letras);
        });
    }

    protected function nomeParaIniciais(): string
    {
        return (string) $this->nome;
    }
}
