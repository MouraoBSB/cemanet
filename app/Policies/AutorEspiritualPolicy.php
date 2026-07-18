<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Policies;

use App\Models\AutorEspiritual;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de AutorEspiritual: permissão autor_espiritual.* (hasPermissionTo, NUNCA can())
 * + escopo por regime (trait). Regime DoTipo (semente DEPAE+DECOM): o responsável é quem está num depto
 * responsável pelo TIPO; o objeto NÃO é consultado. O admin passa antes no Gate::before.
 */
class AutorEspiritualPolicy
{
    use AutorizaPorDepartamento;

    protected function recurso(): string
    {
        return 'autor_espiritual';
    }

    public function ver(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.ver') && $this->noEscopo($user, $autor);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('autor_espiritual.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.editar') && $this->noEscopo($user, $autor);
    }

    public function excluir(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.excluir') && $this->noEscopo($user, $autor);
    }
}
