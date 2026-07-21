<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Conta;

use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Curadoria" no /minha-conta (Fatia F4b). Aba visível ⇔ o
 * usuário ocupa o cargo Diretor do DEPAE OU o cargo Presidente (pertencimento, não capacidade —
 * mesmo molde de AbaMensagens). Memoizada por request via WeakMap (a nav renderiza em TODA
 * página /minha-conta; auth()->user() devolve a mesma instância no request; WeakMap não sofre
 * reuso de spl_object_id).
 *
 * NÃO consulta permission (checkPermissionTo/hasPermissionTo): o eixo aqui é AUTORIA (cargo),
 * não a matriz de capacidades, que segue inerte para o recurso `mensagem`. O admin puro NÃO
 * entra por aqui: esta classe não passa pelo Gate::before — o /admin é o caminho do admin.
 */
class AbaCuradoria
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= $user->ehDiretorDepae() || $user->ehPresidente();
    }
}
