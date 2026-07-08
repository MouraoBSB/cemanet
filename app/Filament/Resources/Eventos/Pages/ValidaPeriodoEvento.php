<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Eventos\Pages;

use App\Support\Eventos\PeriodoEvento;
use Illuminate\Validation\ValidationException;

/**
 * Rede server-side de validação de período (fonte única = PeriodoEvento::erros),
 * complementando as regras de campo do form. Usada por Create e Edit.
 */
trait ValidaPeriodoEvento
{
    protected function validarPeriodo(array $data): array
    {
        $erros = PeriodoEvento::erros(
            $data['data_inicio'] ?? null,
            $data['hora_inicio'] ?? null,
            $data['data_fim'] ?? null,
            $data['hora_fim'] ?? null,
        );

        if ($erros !== []) {
            throw ValidationException::withMessages(['data_inicio' => $erros]);
        }

        return $data;
    }
}
