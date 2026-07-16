<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Policy de Evento nos DOIS eixos:
 * - VISIBILIDADE (quem vê o publicado): view/viewAny, delegadas a podeSerVistoPor / scopeVisiveisPara.
 *   $user é null-safe (visitante anônimo passa por Gate::forUser(null)).
 * - CAPACIDADE (quem edita): ver/criar/editar/excluir — permissão (hasPermissionTo, NUNCA can()) +
 *   escopo de departamento. User NÃO-nulável: o Gate pula o método p/ visitante ⇒ deny limpo.
 * O admin nunca chega às capacidades (passa antes no Gate::before). Filament não usa strict
 * authorization, então a policy parcial de visibilidade segue segura no /admin.
 */
class EventoPolicy
{
    use AutorizaPorDepartamento;

    /** O mesmo literal já hardcodado em 'evento.ver' — zero divergência nova (§9.2). */
    protected function recurso(): string
    {
        return 'evento';
    }

    public function view(?User $user, Evento $evento): bool
    {
        return $evento->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
    }

    public function ver(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.ver') && $this->noEscopo($user, $evento);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('evento.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.editar') && $this->noEscopo($user, $evento);
    }

    public function excluir(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.excluir') && $this->noEscopo($user, $evento);
    }
}
