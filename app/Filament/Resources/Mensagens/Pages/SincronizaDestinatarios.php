<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Filament\Resources\Mensagens\Pages;

use App\Models\Mensagem;
use App\Support\Mensagens\SincronizadorDestinatarios;

/**
 * Extrai o campo `destinatarios` (fora de coluna) do form antes do save e sincroniza o pivô
 * mensagem_destinatario no after-hook. Fora do auto-sync do Filament de propósito (molde
 * SincronizaRelacionadas): o GUARD DE NÍVEL decide o conjunto no servidor — só nível 'direcionada'
 * carrega destinatário; qualquer outro nível ⇒ conjunto VAZIO (limpa o pivô), sem confiar na UI.
 * Determinístico e independente de a UI ter escondido o campo (um Select->relationship() oculto
 * NÃO esvaziaria — vendor). O "≥1 obrigatório" da direcionada é garantido pelo ->required do form.
 *
 * ADAPTADOR FINO (Fatia F4b): a mecânica em si mora em App\Support\Mensagens\SincronizadorDestinatarios
 * — este trait só delega, preservando o contrato (propriedade + assinaturas) para o
 * MensagemDestinatariosGuardTest, que consome $idsDestinatarios por classe anônima.
 */
trait SincronizaDestinatarios
{
    /** @var array<int, int|string> */
    protected array $idsDestinatarios = [];

    protected function capturarDestinatarios(array $data): array
    {
        $this->idsDestinatarios = SincronizadorDestinatarios::filtrarPorNivel(
            $data['nivel'] ?? null,
            $data['destinatarios'] ?? []
        );
        unset($data['destinatarios']); // nunca chega ao model (destinatarios não é coluna)

        return $data;
    }

    protected function aplicarDestinatarios(Mensagem $mensagem): void
    {
        SincronizadorDestinatarios::sincronizar($mensagem, $this->idsDestinatarios);
    }
}
