<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Post: permissão post.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Fase B: saiu do fail-closed.
 */
class PostPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.ver') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('post.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.editar') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }

    public function excluir(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }
}
