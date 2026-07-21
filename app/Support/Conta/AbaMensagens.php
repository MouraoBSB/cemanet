<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Conta;

use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Minhas Mensagens" no /minha-conta (Fatia F4b). Aba visível ⇔
 * o usuário pertence ao setor Médium (pertencimento, não capacidade — molde AbaDirecionadas).
 * Memoizada por request via WeakMap (a nav renderiza em TODA página /minha-conta; auth()->user()
 * devolve a mesma instância no request; WeakMap não sofre reuso de spl_object_id).
 *
 * NÃO consulta permission (checkPermissionTo/hasPermissionTo): o eixo aqui é AUTORIA
 * (setor/cargo), não a matriz de capacidades, que segue inerte para o recurso `mensagem`.
 */
class AbaMensagens
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= $user->ehMedium();
    }
}
