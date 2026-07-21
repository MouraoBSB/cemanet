<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Support\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\User;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Minhas Direcionadas" no /minha-conta.
 * Aba visível ⇔ o usuário é destinatário de ≥1 mensagem DIRECIONADA PUBLICADA (pertencimento, não
 * capacidade): pendente (curadoria F4) OU vínculo a mensagem de outro nível NÃO conta (blindagem O5).
 * Memoizada por request via WeakMap (a nav renderiza em TODA página /minha-conta; auth()->user()
 * devolve a mesma instância no request; WeakMap não sofre reuso de spl_object_id).
 *
 * NÃO consulta permission (checkPermissionTo/AcessoPorTipo) — a Direcionada é por PERTENCIMENTO;
 * por isso o comentário hasPermissionTo-vs-checkPermissionTo do AbaAgenda não se aplica aqui.
 */
class AbaDirecionadas
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap;

        return self::$cache[$user] ??= $user->mensagensDirecionadas()
            ->publicado()
            ->where('nivel', VisibilidadeMensagem::Direcionada->value)
            ->exists();
    }
}
