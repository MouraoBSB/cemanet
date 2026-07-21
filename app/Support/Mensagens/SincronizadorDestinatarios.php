<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Mensagens;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;

/**
 * Mecânica de domínio do pivô mensagem_destinatario — extraída da F4a para uso fora do /admin
 * (o trait SincronizaDestinatarios vira adaptador fino que delega aqui).
 */
class SincronizadorDestinatarios
{
    /**
     * Guard de nível: só 'direcionada' carrega destinatário; qualquer outro nível (ou nível
     * ausente/desconhecido) ⇒ conjunto VAZIO. CRU — não checa se os ids existem; a checagem de
     * existência é responsabilidade exclusiva de aplicar(). Fail-closed por desenho.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int|string>
     */
    public static function filtrarPorNivel(?string $nivel, array $ids): array
    {
        return $nivel === VisibilidadeMensagem::Direcionada->value ? $ids : [];
    }

    /**
     * Sincroniza o pivô com o conjunto dado, sem qualquer filtro adicional (cast + dedup só).
     * Mesma semântica de sempre da aplicarDestinatarios() do trait — usado onde o conjunto já
     * é confiável (ex.: options-list de um Select com ->relationship() no /admin).
     *
     * @param  array<int, int|string>  $ids
     */
    public static function sincronizar(Mensagem $mensagem, array $ids): void
    {
        $mensagem->destinatarios()->sync(
            collect($ids)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );
    }

    /**
     * Guard de nível + filtro de integridade contra `users` ativos + sync — para uso fora do
     * /admin, onde o POST não é confiável (a options-list de um Select é UI, não integridade).
     * Ids inexistentes ou de usuário inativo são descartados ANTES do sync: nunca envolver em
     * try/catch — um id inexistente estouraria QueryException de FK (mensagem_destinatario.user_id).
     *
     * @param  array<int, int|string>  $ids
     */
    public static function aplicar(Mensagem $mensagem, ?string $nivel, array $ids): void
    {
        $idsFiltrados = collect(self::filtrarPorNivel($nivel, $ids))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $idsAtivos = User::query()
            ->whereIn('id', $idsFiltrados)
            ->where('ativo', true)
            ->pluck('id')
            ->all();

        self::sincronizar($mensagem, $idsAtivos);
    }
}
