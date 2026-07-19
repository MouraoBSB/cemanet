<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorMensagensMysql implements LeitorMensagens
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function mensagens(): array
    {
        // publish + pending (exclui o auto-draft). O prefixo wp_ é literal aqui: o select() cru
        // do Laravel NÃO aplica o 'prefix' da conexão (só o query builder aplicaria).
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content, post_status
             FROM wp_posts
             WHERE post_type = 'mensagem-mediunicas' AND post_status IN ('publish', 'pending')"
        );

        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);

            $out[] = [
                'wp_id' => (int) $p->ID,
                'titulo' => $p->post_title,
                'slug' => $p->post_name,                                  // pode vir '' (39 pending)
                'corpo' => $p->post_content ?: null,
                'formato' => $meta['_formato'] ?? null,
                'data_recebimento' => $meta['data_recebimento'] ?? null,  // unix ts
                'nivel' => $this->nivelDe((int) $p->ID),
                'autores_slugs' => $this->autoresSlugsDe((int) $p->ID),
                'fotos_urls' => $this->fotosDe($meta['_fotos_mensagem'] ?? null),
                'link_arquivo' => $meta['link_do_arquivo_mensagem'] ?? null,
                'liberar_download' => $meta['liberar_download_mensagem'] ?? null,
                'status' => $p->post_status === 'publish' ? 'publicado' : 'pendente',
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

    /** Slug do único termo da taxonomia nivel-de-acesso, ou null (49/179 sem termo). */
    private function nivelDe(int $postId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT t.slug
             FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'nivel-de-acesso'
             LIMIT 1",
            [$postId]
        );

        return $row->slug ?? null;
    }

    /**
     * Slugs (post_name) dos autores espirituais via wp_jet_rel_default, rel_id=37 (parent=mensagem).
     *
     * @return array<int, string>
     */
    private function autoresSlugsDe(int $postId): array
    {
        $rows = $this->db->select(
            "SELECT autor.post_name AS slug
             FROM wp_jet_rel_default r
             JOIN wp_posts autor ON autor.ID = r.child_object_id
             WHERE r.rel_id = '37' AND r.parent_object_id = ? AND autor.post_type = 'autores-espirituais'",
            [$postId]
        );

        return array_values(array_filter(array_map(fn ($r) => $r->slug ?: null, $rows)));
    }

    /**
     * URLs das imagens do repeater _fotos_mensagem (PHP serializado, pode ter várias).
     *
     * @return array<int, string>
     */
    private function fotosDe(?string $serializado): array
    {
        if (empty($serializado)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serializado, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if (! is_array($dados)) {
            return [];
        }

        $urls = [];
        foreach ($dados as $item) {
            if (is_array($item) && ! empty($item['url'])) {
                $urls[] = (string) $item['url'];
            }
        }

        return $urls;
    }
}
