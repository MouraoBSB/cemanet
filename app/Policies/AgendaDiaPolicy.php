<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\AgendaDia;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de AgendaDia: permissão agenda.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Fase B: saiu do fail-closed.
 */
class AgendaDiaPolicy
{
    use AutorizaPorDepartamento;

    /** O mesmo literal já hardcodado em 'agenda.ver' — zero divergência nova (§9.2). */
    protected function recurso(): string
    {
        return 'agenda';
    }

    public function ver(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.ver') && $this->noEscopo($user, $agendaDia);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('agenda.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.editar') && $this->noEscopo($user, $agendaDia);
    }

    public function excluir(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.excluir') && $this->noEscopo($user, $agendaDia);
    }
}
