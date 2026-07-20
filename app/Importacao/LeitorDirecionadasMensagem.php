<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

interface LeitorDirecionadasMensagem
{
    /**
     * Uma entrada por mensagem direcionada, com os wp_users.ID dos destinatários.
     *
     * @return array<int, array{wp_id:int, destinatarios_wp_ids:array<int,int>}>
     */
    public function direcionadas(): array;
}
