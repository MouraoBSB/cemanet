<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\AgendaDia;
use App\Models\Departamento;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9b): os 123 AgendaDia estão só em DECOM. Este comando SOMA o DED
 * (syncWithoutDetaching) a cada AgendaDia que já tem DECOM — preserva o DECOM, não migra.
 * Assim DED e DECOM editam TODA a Agenda (decisão 6). Idempotente. Dado de cutover: NÃO auditado.
 */
class SomarDedAgenda extends Command
{
    protected $signature = 'cema:somar-ded-agenda';

    protected $description = 'Soma o DED ao N:N de cada AgendaDia que já tem DECOM (preserva DECOM), idempotente.';

    public function handle(): int
    {
        $decomId = Departamento::where('sigla', 'DECOM')->value('id');
        $dedId = Departamento::where('sigla', 'DED')->value('id');

        if (! $decomId || ! $dedId) {
            $this->error('Departamentos DED/DECOM não encontrados — rode o EstruturaCemaSeeder.');

            return self::FAILURE;
        }

        $registros = AgendaDia::whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $decomId))->get();

        foreach ($registros as $registro) {
            $registro->departamentos()->syncWithoutDetaching([$dedId]);
        }

        $this->info(sprintf('%d dia(s) da agenda com DED somado (DECOM preservado).', $registros->count()));

        return self::SUCCESS;
    }
}
