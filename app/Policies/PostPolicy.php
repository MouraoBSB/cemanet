<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Fail-closed: Post ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (Post não é TemDepartamento).
 */
class PostPolicy
{
    public function ver(User $user, Post $post): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, Post $post): bool
    {
        return false;
    }

    public function excluir(User $user, Post $post): bool
    {
        return false;
    }
}
