<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\AgendaDia;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de AgendaDia: permissão agenda.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Fase B: saiu do fail-closed.
 */
class AgendaDiaPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.ver') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }

    public function criar(User $user): bool
    {
        return $user->checkPermissionTo('agenda.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.editar') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }

    public function excluir(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }
}
