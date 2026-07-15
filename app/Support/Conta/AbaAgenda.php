<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Support\Conta;

use App\Models\AgendaDia;
use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Agenda" no /minha-conta (§6.1 do spec).
 * Usada por: a nav (mostrar/ocultar), o ContaController@agenda (abort_unless) e o mount do
 * componente. Aba visível ⇔ capacidade de ver + registro no escopo (decisão 1) — senão apareceria
 * vazia para todo diretor. Capacidade checada ANTES da query (curto-circuito em memória).
 *
 * Memoizada por request via WeakMap pelo objeto User (a nav renderiza em TODA página /minha-conta;
 * auth()->user() devolve a mesma instância no request). WeakMap não sofre reuso de spl_object_id.
 *
 * FAIL-CLOSED se a capacidade nem existe no catálogo: sem CapacidadesSeeder (ambiente/testes que
 * não semeiam as permissions), hasPermissionTo lançaria PermissionDoesNotExist e QUEBRARIA a nav
 * de todas as páginas de conta. O catch devolve false — a aba some, a nav não quebra.
 */
class AbaAgenda
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= self::calcular($user);
    }

    private static function calcular(User $user): bool
    {
        if (! $user->checkPermissionTo('agenda.ver')) {
            return false;
        }

        return AgendaDia::noEscopoDe($user)->exists();
    }
}
