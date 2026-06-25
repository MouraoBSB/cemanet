<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Carbon;

class TransformadorLegado
{
    public const FUSO = 'America/Sao_Paulo';

    public static function statusParaAtivo(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['true', 'on', '1', 'sim'], true);
    }

    public static function unixParaData(int|string|null $unix): ?Carbon
    {
        $ts = (int) $unix;

        return $ts > 0 ? Carbon::createFromTimestamp($ts)->setTimezone(self::FUSO) : null;
    }

    public static function destaquesDoRepeater(?string $serializado): array
    {
        if (empty($serializado)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serializado);
        } finally {
            restore_error_handler();
        }

        if (! is_array($dados)) {
            return [];
        }

        $destaques = [];
        $ordem = 0;
        foreach ($dados as $item) {
            if (! is_array($item)) {
                continue;
            }
            $destaque = trim((string) ($item['destaque'] ?? ''));
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($destaque === '' && $texto === '') {
                continue;
            }
            $destaques[] = ['destaque' => $destaque, 'texto' => $texto, 'ordem' => $ordem++];
        }

        return $destaques;
    }
}
