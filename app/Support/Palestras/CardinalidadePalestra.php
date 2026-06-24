<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Support\Palestras;

class CardinalidadePalestra
{
    /**
     * Valida a regra de negócio: 1–2 palestrantes (obrigatório) e 0–1 diretor (opcional).
     * Retorna as mensagens de erro (vazio = válido).
     */
    public static function erros(int $palestrantes, int $diretores): array
    {
        $erros = [];

        if ($palestrantes < 1 || $palestrantes > 2) {
            $erros[] = 'A palestra deve ter 1 ou 2 palestrantes.';
        }

        if ($diretores > 1) {
            $erros[] = 'A palestra pode ter no máximo 1 diretor.';
        }

        return $erros;
    }
}
