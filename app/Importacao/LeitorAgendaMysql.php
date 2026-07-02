<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorAgendaMysql implements LeitorAgenda
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function entradas(): array
    {
        // Somente publish/future; ORDER BY ID ASC => o post original (ID menor) vem
        // primeiro; o dedupe do importador mantém esse (slug limpo) e trata a cópia
        // (-2) só como 301.
        $rows = $this->db->select(
            "SELECT ID, post_name, DATE(post_date) AS data_post
             FROM wp_posts
             WHERE post_type = 'agenda-reforma'
               AND post_status IN ('publish', 'future')
             ORDER BY ID ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            // Ignora sobras de lixeira (slug terminando em __trashed); filtro em PHP
            // evita depender de escape de curinga em LIKE.
            if (str_ends_with($r->post_name, '__trashed')) {
                continue;
            }

            $id = (int) $r->ID;
            $meta = $this->metasDe($id);
            $dataPost = $r->data_post; // 'AAAA-MM-DD'
            $avisos = [];

            // Cruza DATE(post_date) x _dia_agenda (unix -> data). Diverge? avisa (usa post_date).
            $dataUnix = TransformadorLegado::unixParaData($meta['_dia_agenda'] ?? null);
            if ($dataUnix !== null && $dataUnix->format('Y-m-d') !== $dataPost) {
                $avisos[] = "[{$r->post_name}] data divergente: post_date={$dataPost} vs _dia_agenda={$dataUnix->format('Y-m-d')} (usado post_date).";
            }

            // Resolve chaves de glossary (maio); chave crua *_2026 não mapeada -> null + aviso.
            $mes = GlossarioAgenda::resolver($meta['_mes_titulo'] ?? null);
            if ($mes['aviso'] !== null) {
                $avisos[] = "[{$r->post_name}] {$mes['aviso']}";
            }
            $metaDia = GlossarioAgenda::resolver($meta['_titulo_meta_dia'] ?? null);
            if ($metaDia['aviso'] !== null) {
                $avisos[] = "[{$r->post_name}] {$metaDia['aviso']}";
            }

            $out[] = [
                'data' => $dataPost,
                'wp_id' => $id,
                'post_name' => $r->post_name,
                'reflexao' => $meta['_reflexao'] ?? null,
                'mes_titulo' => $mes['valor'],
                'mes_texto' => $meta['_mes_texto'] ?? null,
                'meta_dia_titulo' => $metaDia['valor'],
                'meta_dia_texto' => $meta['_dia'] ?? null,
                'prece' => $meta['_prece'] ?? null,
                'avisos' => $avisos,
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (primeiro valor) */
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
}
