<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Models\Mensagem;

/**
 * Extrai o campo `relacionadas` (fora de coluna) do form antes do save e aplica a sincronização
 * SIMÉTRICA (dual-espelhada) no after-hook. Fora do auto-sync do Filament de propósito: o
 * ->relationship() gravaria só um sentido. Mesmo molde de SincronizaPessoas (Palestra).
 */
trait SincronizaRelacionadas
{
    /** @var array<int, int|string> */
    protected array $idsRelacionadas = [];

    protected function capturarRelacionadas(array $data): array
    {
        $this->idsRelacionadas = $data['relacionadas'] ?? [];
        unset($data['relacionadas']);

        return $data;
    }

    protected function aplicarRelacionadas(Mensagem $mensagem): void
    {
        $mensagem->sincronizarRelacionadas($this->idsRelacionadas);
    }
}
