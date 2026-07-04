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

        if ($ts <= 0) {
            return null;
        }

        // O legado (JetEngine) guarda o horário de parede local (ex.: 19:00) como se
        // fosse UTC. Lemos esse "relógio" em UTC e o reinterpretamos como horário de
        // São Paulo, sem deslocar — senão a palestra das 19h apareceria às 16h.
        $parede = Carbon::createFromTimestamp($ts, 'UTC');

        return Carbon::create(
            $parede->year,
            $parede->month,
            $parede->day,
            $parede->hour,
            $parede->minute,
            $parede->second,
            self::FUSO,
        );
    }

    public static function destaquesDoRepeater(?string $serializado): array
    {
        if (empty($serializado)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serializado, ['allowed_classes' => false]);
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
