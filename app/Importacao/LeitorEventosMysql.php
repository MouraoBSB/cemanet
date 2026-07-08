<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorEventosMysql implements LeitorEventos
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function eventos(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_excerpt, post_content
             FROM wp_posts
             WHERE post_type = '_evento' AND post_status = 'publish'"
        );

        $out = [];
        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $meta = $this->metasDe($id);

            $thumbId = isset($meta['_thumbnail_id']) && $meta['_thumbnail_id'] !== ''
                ? (int) $meta['_thumbnail_id']
                : null;

            $out[] = [
                'wp_id' => $id,
                'titulo' => $p->post_title,
                'slug' => $p->post_name,
                'resumo' => ($p->post_excerpt !== '' && $p->post_excerpt !== null) ? $p->post_excerpt : null,
                'conteudo' => $p->post_content ?: null,
                'data_do_evento' => $meta['data_do_evento'] ?? null,
                'evento_publico' => $meta['evento_publico'] ?? null,
                'mostrar_horario' => $meta['mostrar_horario'] ?? null,
                'mostrar_horario_definido' => array_key_exists('mostrar_horario', $meta),
                'local' => (($meta['local'] ?? '') !== '') ? $meta['local'] : null,
                'flyer_url' => $thumbId ? $this->urlDaImagem($thumbId) : null,
                'galeria_urls' => $this->galeriaUrls($meta['_galeria-de-imagens'] ?? null),
                'departamentos_siglas' => $this->siglasDepartamento($id),
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (1º valor por chave; resolve duplicatas) */
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

    /** CSV de IDs de attachment → URLs (guid) na ordem, ignorando os não resolvidos. */
    private function galeriaUrls(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $urls = [];
        foreach (explode(',', $csv) as $raw) {
            $attId = (int) trim($raw);
            if ($attId <= 0) {
                continue;
            }
            $url = $this->urlDaImagem($attId);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** Nomes dos termos (= siglas de departamento) da taxonomia _departamentos_tax do evento. */
    private function siglasDepartamento(int $postId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT t.name FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = '_departamentos_tax'",
            [$postId]
        );

        return array_values(array_map(fn ($r) => trim((string) $r->name), $rows));
    }
}
