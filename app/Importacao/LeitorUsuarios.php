<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

interface LeitorUsuarios
{
    /** @return iterable<int, array<string, mixed>> */
    public function usuarios(): iterable;
}
