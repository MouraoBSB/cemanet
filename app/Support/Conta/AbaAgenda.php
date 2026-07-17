<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Support\Conta;

use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Agenda" no /minha-conta.
 * Usada por: a nav (mostrar/ocultar), o ContaController@agenda (abort_unless) e o mount do
 * componente. Aba visível ⇔ capacidade de ver + "sou responsável pelo tipo" (a pergunta única
 * do AcessoPorTipo). NÃO consulta registro: a config já restringe a quem mantém a agenda, e
 * consultar perpetuaria o furo do 1º registro (tabela vazia ⇒ aba some ⇒ ninguém cria o
 * primeiro dia).
 *
 * Memoizada por request via WeakMap pelo objeto User (a nav renderiza em TODA página
 * /minha-conta; auth()->user() devolve a mesma instância no request). WeakMap não sofre reuso
 * de spl_object_id.
 *
 * Sinal de nav ⇒ checkPermissionTo, NUNCA hasPermissionTo: o fail-closed é do próprio spatie
 * (HasPermissions::checkPermissionTo captura PermissionDoesNotExist e devolve false). Com
 * hasPermissionTo, um ambiente sem CapacidadesSeeder derrubaria a nav de todas as páginas.
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

        return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, 'agenda');
    }
}
