<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

interface LeitorBlog
{
    /**
     * Retorna todos os posts do blog lidos do legado, normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function posts(): array;
}
