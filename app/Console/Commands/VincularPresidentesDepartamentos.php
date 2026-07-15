<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9c): materializa "presidente edita tudo" (decisão de Fase C). Usuários
 * com o cargo institucional 'diretor_presidente' (slug 'presidente') recebem os 8 departamentos
 * (syncWithoutDetaching). O backfill VincularDiretoresDepartamento não os alcança (cargo sem
 * departamento_id). Idempotente; audita o vínculo. NUNCA destrutivo.
 */
class VincularPresidentesDepartamentos extends Command
{
    protected $signature = 'cema:vincular-presidentes-departamentos';

    protected $description = 'Vincula os presidentes (cargo diretor_presidente) aos 8 departamentos (idempotente).';

    public function handle(): int
    {
        $todosIds = Departamento::pluck('id')->all();
        $todosNomes = Departamento::pluck('nome', 'id')->all();

        $presidentes = User::whereHas('cargos', fn ($q) => $q->where('cargos.slug', 'presidente'))->get();

        foreach ($presidentes as $presidente) {
            $antes = $presidente->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();
            $presidente->departamentos()->syncWithoutDetaching($todosIds);
            AuditoriaAutorizacao::registrarDepartamentosUsuario($presidente, $antes, $todosNomes);
        }

        $this->info(sprintf('%d presidente(s) vinculado(s) aos %d departamentos.', $presidentes->count(), count($todosIds)));

        return self::SUCCESS;
    }
}
