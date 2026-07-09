<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;

/**
 * Policy parcial (só view/viewAny) — segura porque o Filament NÃO usa strict authorization
 * (isAuthorizationStrict = false por padrão; o projeto não altera), então create/update/delete
 * no /admin seguem permitidos. ⚠️ Se um dia ligarem strictAuthorization, esta policy parcial
 * passará a lançar LogicException nos métodos ausentes — adicione-os então.
 */
class EventoPolicy
{
    /** Delegada à regra única do model; $user é null-safe (visitante anônimo passa por Gate::forUser(null)). */
    public function view(?User $user, Evento $evento): bool
    {
        return $evento->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
    }
}
