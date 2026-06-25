<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

interface LeitorLegado
{
    /** @return array<int, array<string, mixed>> */
    public function assuntos(): array;

    /** @return array<int, array<string, mixed>> */
    public function palestrantes(): array;

    /** @return array<int, array<string, mixed>> */
    public function palestras(): array;
}
