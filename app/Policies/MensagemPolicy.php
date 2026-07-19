<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Policies;

use App\Models\Mensagem;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Mensagem: permissão mensagem.* (hasPermissionTo, NUNCA can())
 * + escopo por regime (trait). Regime DoTipo (semente DEPAE): o responsável é quem está num depto
 * responsável pelo TIPO; o objeto NÃO é consultado. O admin passa antes no Gate::before.
 * Nasce INERTE (só admin edita via /admin nesta fatia). O eixo de autoria do médium
 * (mensagem.publicar / definir-nivel) é outro eixo — Fatia 4.
 */
class MensagemPolicy
{
    use AutorizaPorDepartamento;

    protected function recurso(): string
    {
        return 'mensagem';
    }

    public function ver(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.ver') && $this->noEscopo($user, $mensagem);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('mensagem.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.editar') && $this->noEscopo($user, $mensagem);
    }

    public function excluir(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.excluir') && $this->noEscopo($user, $mensagem);
    }
}
