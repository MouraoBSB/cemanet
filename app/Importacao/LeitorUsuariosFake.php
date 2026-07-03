<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

class LeitorUsuariosFake implements LeitorUsuarios
{
    /** @param array<int, array<string, mixed>> $itens */
    public function __construct(private array $itens = []) {}

    public function usuarios(): iterable
    {
        return $this->itens;
    }
}
