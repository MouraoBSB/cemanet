<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\AgendaDia;
use App\Models\User;

/**
 * Fail-closed: AgendaDia ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (AgendaDia não é TemDepartamento).
 */
class AgendaDiaPolicy
{
    public function ver(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }

    public function excluir(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }
}
