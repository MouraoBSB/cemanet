<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorDirecionadasMensagemMysql implements LeitorDirecionadasMensagem
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function direcionadas(): array
    {
        // rel 38 = DIRECIONADA, direção REVERSA: parent = usuário, child = mensagem.
        // JOIN wp_users garante que o parent é usuário (descarta os IDs que coincidem com posts);
        // JOIN wp_posts (post_type) garante que o child é mensagem. Prefixo wp_ literal (select cru).
        $rows = $this->db->select(
            "SELECT r.child_object_id AS wp_id, r.parent_object_id AS wp_user_id
             FROM wp_jet_rel_default r
             JOIN wp_users u ON u.ID = r.parent_object_id
             JOIN wp_posts m ON m.ID = r.child_object_id AND m.post_type = 'mensagem-mediunicas'
             WHERE r.rel_id = '38'"
        );

        $porMensagem = [];
        foreach ($rows as $r) {
            $porMensagem[(int) $r->wp_id][] = (int) $r->wp_user_id;
        }

        $out = [];
        foreach ($porMensagem as $wpId => $userIds) {
            $out[] = ['wp_id' => $wpId, 'destinatarios_wp_ids' => array_values(array_unique($userIds))];
        }

        return $out;
    }
}
