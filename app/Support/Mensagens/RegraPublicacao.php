<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Mensagens;

use App\Enums\VisibilidadeMensagem;

class RegraPublicacao
{
    /**
     * Valida a regra de negócio de publicação: nível válido e, se `direcionada`, ≥1 destinatário.
     * Retorna as mensagens de erro (vazio = válido) — NUNCA lança. Molde de
     * App\Support\Palestras\CardinalidadePalestra::erros: testado em Unit puro (sem app Laravel
     * bootado), onde ValidationException::withMessages() não funciona (depende da facade Validator).
     * Quem lança é o chamador (componente Livewire), com a chave do statePath.
     */
    public static function erros(array $dados): array
    {
        $erros = [];

        // (string) casta null para '' — tryFrom('') também é null (slug inválido): fail-closed único.
        $visibilidade = VisibilidadeMensagem::tryFrom((string) ($dados['nivel'] ?? ''));

        if ($visibilidade === null) {
            $erros[] = 'Selecione um nível de visibilidade válido.';

            return $erros; // sem nível válido, não há como avaliar a regra de destinatário
        }

        if ($visibilidade === VisibilidadeMensagem::Direcionada && empty($dados['destinatarios'])) {
            $erros[] = 'Para uma mensagem direcionada, selecione ao menos um destinatário.';
        }

        return $erros;
    }
}
