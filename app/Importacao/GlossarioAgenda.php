<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

class GlossarioAgenda
{
    /**
     * Resolução das chaves cruas do glossary do JetEngine (só maio de 2026 as usa).
     * Jun/jul/ago já trazem texto puro. Fonte: introspecção do legado (spec §3).
     */
    public const MAPA = [
        'maio_2026' => 'Desenvolver abnegação, renúncia e solidariedade',
        'meta_dia_maio_2026_01' => 'Desenvolver Abnegação',
        'meta_dia_maio_2026_02' => 'Desenvolver a Renúncia',
        'meta_dia_maio_2026_03' => 'Desenvolver Renúncia no Lar',
        'meta_dia_maio_2026_04' => 'Desenvolver a Solidariedade',
    ];

    /**
     * Resolve uma chave de glossary para o texto final.
     * - chave conhecida  -> texto do MAPA, sem aviso;
     * - chave crua "*_2026[_NN]" não mapeada -> null + aviso (nunca grava a chave crua);
     * - texto puro (ou null) -> passa como está.
     *
     * @return array{valor: ?string, aviso: ?string}
     */
    public static function resolver(?string $valor): array
    {
        if ($valor === null) {
            return ['valor' => null, 'aviso' => null];
        }

        if (array_key_exists($valor, self::MAPA)) {
            return ['valor' => self::MAPA[$valor], 'aviso' => null];
        }

        // parece uma chave crua de 2026 (ex.: "setembro_2026", "meta_dia_x_2026_03") não mapeada
        if (preg_match('/_2026(_\d+)?$/', $valor) === 1) {
            return [
                'valor' => null,
                'aviso' => "Chave de glossary não resolvida: '{$valor}' (gravado null).",
            ];
        }

        return ['valor' => $valor, 'aviso' => null];
    }
}
