<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies\Concerns;

use App\Models\Contracts\TemDepartamento;
use App\Models\User;

/**
 * Molde do escopo por departamento das policies de capacidade (fonte única).
 * Fail-closed: usuário sem departamento OU objeto sem departamento ⇒ false.
 */
trait AutorizaPorDepartamento
{
    protected function objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool
    {
        $idsUsuario = $user->departamentos()->pluck('departamentos.id')->all();

        if ($idsUsuario === []) {
            return false;
        }

        return $objeto->departamentos()
            ->whereIn('departamentos.id', $idsUsuario)
            ->exists();
    }
}
