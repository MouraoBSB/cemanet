<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

interface LeitorEventos
{
    /**
     * Retorna todos os eventos lidos do legado, normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventos(): array;
}
