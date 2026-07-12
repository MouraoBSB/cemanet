<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestrante;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Palestrante: permissão palestrante.* (hasPermissionTo, NUNCA can()) + escopo
 * de departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Palestrante é o cadastro
 * único (serve palestrante E diretor-de-palestra via papel no pivot palestra_pessoa); o departamento é de
 * POSSE do cadastro (DED), não do papel.
 */
class PalestrantePolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.ver') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('palestrante.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.editar') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }

    public function excluir(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }
}
