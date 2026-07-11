<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestra;
use App\Models\User;

/**
 * Fail-closed: Palestra ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (Palestra não é TemDepartamento).
 */
class PalestraPolicy
{
    public function ver(User $user, Palestra $palestra): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, Palestra $palestra): bool
    {
        return false;
    }

    public function excluir(User $user, Palestra $palestra): bool
    {
        return false;
    }
}
