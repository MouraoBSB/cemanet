<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Policies;

use App\Models\Mensagem;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Policy de Mensagem nos DOIS eixos:
 * - VISIBILIDADE (quem vê): view/viewAny, delegadas a podeSerVistoPor / scopeVisiveisPara.
 *   $user é null-safe (visitante anônimo passa por Gate::forUser(null)).
 * - CAPACIDADE (quem edita): ver/criar/editar/excluir — permissão mensagem.* (hasPermissionTo, NUNCA can())
 *   + escopo por regime DoTipo (trait). Nasce INERTE (só admin edita via /admin). O eixo de autoria do
 *   médium (mensagem.publicar / definir-nivel) é outro eixo — Fatia 4. O admin passa antes no Gate::before.
 */
class MensagemPolicy
{
    use AutorizaPorDepartamento;

    protected function recurso(): string
    {
        return 'mensagem';
    }

    public function view(?User $user, Mensagem $mensagem): bool
    {
        return $mensagem->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
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
