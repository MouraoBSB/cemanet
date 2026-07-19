<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

interface LeitorMensagens
{
    /**
     * Mensagens mediúnicas lidas do legado, normalizadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mensagens(): array;
}
