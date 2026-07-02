<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

interface LeitorAgenda
{
    /**
     * Uma linha por post válido (publish/future) do CPT agenda-reforma, já normalizada.
     *
     * @return array<int, array{
     *     data: string, wp_id: int, post_name: string, reflexao: ?string,
     *     mes_titulo: ?string, mes_texto: ?string, meta_dia_titulo: ?string,
     *     meta_dia_texto: ?string, prece: ?string, avisos: string[]
     * }>
     */
    public function entradas(): array;
}
