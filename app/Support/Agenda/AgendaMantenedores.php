<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Support\Agenda;

use App\Models\Departamento;

/**
 * Departamentos que MANTÊM a Agenda da Reforma Íntima = DED + DECOM (§7 O1 do spec).
 * Todo AgendaDia criado pelo site nasce vinculado a ESTES departamentos, independente do
 * autor — para que DED e DECOM editem TODA a Agenda (decisão 6). Resolvidos por sigla
 * (determinístico), não por id numérico.
 */
class AgendaMantenedores
{
    public const SIGLAS = ['DED', 'DECOM'];

    /** @return array<int> ids dos departamentos mantenedores. */
    public static function ids(): array
    {
        return Departamento::whereIn('sigla', self::SIGLAS)->pluck('id')->all();
    }
}
