<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Backfill idempotente do VÍNCULO editorial dos diretores: cada usuário que ocupa um cargo COM
 * departamento (cargos.departamento_id NOT NULL) recebe esse departamento em departamento_usuario.
 * O filtro é semântico ("cargo com departamento" = candidato a editor), não o slug "diretor_*".
 * Só o vínculo; a permissão é a Fase C. O DAS pode ficar sem diretor (cargo sem ocupante) — esperado.
 */
class VincularDiretoresDepartamento extends Command
{
    protected $signature = 'cema:vincular-diretores-departamento';

    protected $description = 'Vincula cada usuário ao(s) departamento(s) do(s) seu(s) cargo(s) com departamento (departamento_usuario), idempotente.';

    public function handle(): int
    {
        $usuarios = User::whereHas('cargos', fn ($q) => $q->whereNotNull('departamento_id'))
            ->with(['cargos' => fn ($q) => $q->whereNotNull('departamento_id')])
            ->get();

        $totalVinculos = 0;
        foreach ($usuarios as $usuario) {
            $departamentoIds = $usuario->cargos->pluck('departamento_id')->unique()->all();
            $usuario->departamentos()->syncWithoutDetaching($departamentoIds);
            $totalVinculos += count($departamentoIds);
        }

        $this->info(sprintf('%d diretor(es) vinculado(s) — %d vínculo(s) de departamento.', $usuarios->count(), $totalVinculos));

        return self::SUCCESS;
    }
}
