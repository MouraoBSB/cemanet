<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Support\Palestras;

class DuracaoPalestra
{
    public const PADRAO_MIN = 90;

    /** Converte uma duração livre ("≈1h10", "45 min", "2h") em minutos; fallback 90. */
    public static function minutos(?string $duracao): int
    {
        if ($duracao === null || trim($duracao) === '') {
            return self::PADRAO_MIN;
        }

        $s = mb_strtolower($duracao);
        $min = 0;

        if (preg_match('/(\d+)\s*h(?:\s*(\d+))?/', $s, $m)) {
            $min += (int) $m[1] * 60;
            if (isset($m[2]) && $m[2] !== '') {
                $min += (int) $m[2];
            }
        } elseif (preg_match('/(\d+)\s*min/', $s, $m)) {
            $min += (int) $m[1];
        }

        return $min > 0 ? $min : self::PADRAO_MIN;
    }
}
