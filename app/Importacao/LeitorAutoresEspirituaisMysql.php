<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorAutoresEspirituaisMysql implements LeitorAutoresEspirituais
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function autores(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content
             FROM wp_posts
             WHERE post_type = 'autores-espirituais' AND post_status = 'publish'"
        );

        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);

            $thumbId = isset($meta['_thumbnail_id']) && $meta['_thumbnail_id'] !== ''
                ? (int) $meta['_thumbnail_id']
                : null;

            $out[] = [
                'slug' => $p->post_name,
                'nome' => $p->post_title,
                'bio' => $p->post_content ?: null,
                'foto_url' => $thumbId ? $this->urlDaImagem($thumbId) : null,
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (1º valor por chave) */
    private function metasDe(int $postId): array
    {
        $rows = $this->db->select('SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?', [$postId]);
        $m = [];
        foreach ($rows as $r) {
            if (! array_key_exists($r->meta_key, $m)) {
                $m[$r->meta_key] = $r->meta_value;
            }
        }

        return $m;
    }

    /** URL (guid) de um attachment pelo ID. */
    private function urlDaImagem(int $attId): ?string
    {
        $row = $this->db->selectOne(
            'SELECT guid FROM wp_posts WHERE ID = ? AND post_type = ? LIMIT 1',
            [$attId, 'attachment']
        );

        return $row->guid ?? null;
    }
}
