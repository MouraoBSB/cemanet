<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorResumosMensagensMysql implements LeitorResumosMensagens
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function resumos(): array
    {
        // Medido em 21/07: 154 preenchidos (125 publish + 29 pending), zero HTML, zero
        // entidades, zero shortcodes. O prefixo wp_ é literal: select() cru não aplica o
        // 'prefix' da conexão (molde LeitorMensagensMysql:21-27).
        $posts = $this->db->select(
            "SELECT ID, post_excerpt
             FROM wp_posts
             WHERE post_type = 'mensagem-mediunicas'
               AND post_status IN ('publish', 'pending')
               AND TRIM(post_excerpt) <> ''
             ORDER BY ID"
        );

        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'wp_id' => (int) $p->ID,
                // normaliza '' => null (molde LeitorBlogMysql:68): sem isso o critério
                // "resumo vazio" do comando deixaria de ser detectável no re-run.
                'resumo' => ($p->post_excerpt !== '' && $p->post_excerpt !== null) ? $p->post_excerpt : null,
            ];
        }

        return $out;
    }
}
