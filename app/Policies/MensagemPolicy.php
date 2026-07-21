<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Policies;

use App\Models\Mensagem;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Policy de Mensagem em TRÊS eixos:
 * - VISIBILIDADE (quem vê): view/viewAny, delegadas a podeSerVistoPor / scopeVisiveisPara.
 *   $user é null-safe (visitante anônimo passa por Gate::forUser(null)).
 * - CAPACIDADE (quem edita): ver/criar/editar/excluir — permissão mensagem.* (hasPermissionTo, NUNCA can())
 *   + escopo por regime DoTipo (trait). Nasce INERTE (só admin edita via /admin).
 * - AUTORIA (F4b — médium lança, diretor do DEPAE/presidente cura): lancar/editarPendente/curar/
 *   editarNaCuradoria/publicar — pertencimento por setor/cargo (ehMedium/ehDiretorDepae/ehPresidente),
 *   NUNCA hasPermissionTo. O admin passa antes no Gate::before em todos os eixos.
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

    /** Eixo AUTORIA (F4b) — pertencimento por setor/cargo, NUNCA capacidade/matriz. Admin passa antes no Gate::before. */
    public function lancar(User $user): bool
    {
        return $user->ehMedium();
    }

    public function editarPendente(User $user, Mensagem $mensagem): bool
    {
        return $user->ehMedium()
            && $mensagem->medium_id === $user->id
            && $mensagem->status === Mensagem::STATUS_PENDENTE;
    }

    /** Portão da ABA/rota da curadoria (sem objeto). */
    public function curar(User $user): bool
    {
        return $user->ehDiretorDepae() || $user->ehPresidente();
    }

    /** Portão de CADA registro na curadoria: só pendente (O7 — publicada é /admin). */
    public function editarNaCuradoria(User $user, Mensagem $mensagem): bool
    {
        return $this->curar($user) && $mensagem->status === Mensagem::STATUS_PENDENTE;
    }

    public function publicar(User $user, Mensagem $mensagem): bool
    {
        return $this->editarNaCuradoria($user, $mensagem);
    }
}
