<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario;

use App\Models\User;
use Illuminate\Support\Collection;

interface FonteCalendario
{
    /** Identificador do tipo: 'palestra' | 'evento' (| 'agenda' no futuro). */
    public function tipo(): string;

    /**
     * Meses 'Y-m' com ocorrência VISÍVEL no modo, em ordem ascendente.
     *
     * @return list<string>
     */
    public function meses(string $modo, ?User $u): array;

    /**
     * Ocorrências VISÍVEIS que TOCAM (ano,mês) no modo, já como DTO.
     *
     * @return Collection<int, OcorrenciaCalendario>
     */
    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection;

    /** Próxima ocorrência FUTURA visível (hero/countdown); null se não houver. */
    public function proxima(?User $u): ?OcorrenciaCalendario;
}
