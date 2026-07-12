<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestra;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Palestra: permissão palestra.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável ⇒ o Gate pula p/ visitante (deny limpo). O admin passa antes no
 * Gate::before. Fase B: saiu do fail-closed ao Palestra ganhar departamento (implements TemDepartamento).
 */
class PalestraPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.ver') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('palestra.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.editar') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }

    public function excluir(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }
}
