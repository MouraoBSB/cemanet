<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

interface LeitorAutoresEspirituais
{
    /**
     * Autores espirituais lidos do legado, normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function autores(): array;
}
