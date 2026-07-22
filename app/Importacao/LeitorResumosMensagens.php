<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Importacao;

/**
 * Contrato PRÓPRIO (não um método a mais em LeitorMensagens): aquela interface tem 2 fakes
 * anônimos nos testes, e acrescentar método a ela é erro fatal de PHP, não falha de asserção.
 */
interface LeitorResumosMensagens
{
    /** @return array<int, array{wp_id: int, resumo: ?string}> */
    public function resumos(): array;
}
