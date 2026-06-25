<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorLegadoMysql implements LeitorLegado
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function assuntos(): array
    {
        $rows = $this->db->select(
            "SELECT t.term_id, t.name, t.slug, tt.parent
             FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'assuntos-principais'"
        );
        // mapa term_id -> slug para resolver o parent
        $slugPorId = [];
        foreach ($rows as $r) {
            $slugPorId[(int) $r->term_id] = $r->slug;
        }

        return array_map(fn ($r) => [
            'nome' => $r->name,
            'slug' => $r->slug,
            'parent_slug' => ((int) $r->parent) > 0 ? ($slugPorId[(int) $r->parent] ?? null) : null,
        ], $rows);
    }

    public function palestrantes(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content
             FROM wp_posts WHERE post_type='palestrantes' AND post_status='publish'"
        );
        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);
            $out[] = [
                'nome' => $p->post_title,
                'slug' => $p->post_name,
                'bio' => $p->post_content ?: null,
                'email' => $meta['email_palestrante'] ?? null,
                'telefone' => $meta['telefone_palestrante'] ?? null,
                'mostrar_email' => TransformadorLegado::statusParaAtivo($meta['mostrar_email_palestrante'] ?? null),
                'mostrar_telefone' => TransformadorLegado::statusParaAtivo($meta['mostrar_telefone_palestrante'] ?? null),
                'ativo' => TransformadorLegado::statusParaAtivo($meta['status_palestrante'] ?? null),
                'foto_url' => $this->urlDaImagem((int) $p->ID),
            ];
        }

        return $out;
    }

    public function palestras(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_excerpt, post_content, post_status
             FROM wp_posts WHERE post_type='palestra_publica' AND post_status='publish'"
        );
        $out = [];
        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $meta = $this->metasDe($id);
            $out[] = [
                'titulo' => $p->post_title,
                'slug' => $p->post_name,
                'subtitulo' => $p->post_excerpt ?: null,
                'resumo' => $meta['descricao'] ?? null,
                'descricao' => $p->post_content ?: null,
                'data_da_palestra' => TransformadorLegado::unixParaData($meta['data_da_palestra'] ?? null),
                'online' => TransformadorLegado::statusParaAtivo($meta['palestra_online'] ?? null),
                'link_youtube' => $meta['link_do_youtube'] ?? null,
                'cor_fundo' => $meta['escolher_cor_do_fundo'] ?? null,
                'publico_online' => isset($meta['publico_online']) && $meta['publico_online'] !== '' ? (int) $meta['publico_online'] : null,
                'publico_presencial' => isset($meta['publico_presencial']) && $meta['publico_presencial'] !== '' ? (int) $meta['publico_presencial'] : null,
                'publico_total' => isset($meta['publico_total']) && $meta['publico_total'] !== '' ? (int) $meta['publico_total'] : null,
                'status' => 'publicado',
                'palestrantes_slugs' => $this->slugsRelacionados(107, 'child', $id),  // 107: child=palestra, parent=palestrante
                'diretor_slug' => $this->slugsRelacionados(108, 'parent', $id)[0] ?? null, // 108: parent=palestra, child=diretor
                'assuntos_slugs' => $this->assuntosDaPalestra($id),
                'destaques' => TransformadorLegado::destaquesDoRepeater($meta['assuntos_principais'] ?? null),
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

    private function urlDaImagem(int $postId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT a.guid FROM wp_postmeta tm
             JOIN wp_posts a ON a.ID = tm.meta_value
             WHERE tm.post_id = ? AND tm.meta_key = '_thumbnail_id' LIMIT 1",
            [$postId]
        );

        return $row->guid ?? null;
    }

    /**
     * Slugs de wp_posts ligados a $palestraId pela relação $relId.
     * $ladoDaPalestra = 'child' (rel 107, palestra é child) ou 'parent' (rel 108, palestra é parent).
     * Retorna os slugs do OUTRO lado (a pessoa).
     *
     * @return array<int,string>
     */
    private function slugsRelacionados(int $relId, string $ladoDaPalestra, int $palestraId): array
    {
        $tabela = "wp_jet_rel_{$relId}";
        [$colPalestra, $colPessoa] = $ladoDaPalestra === 'child'
            ? ['child_object_id', 'parent_object_id']
            : ['parent_object_id', 'child_object_id'];

        $rows = $this->db->select(
            "SELECT pessoa.post_name AS slug
             FROM {$tabela} r JOIN wp_posts pessoa ON pessoa.ID = r.{$colPessoa}
             WHERE r.{$colPalestra} = ? AND pessoa.post_type = 'palestrantes'",
            [$palestraId]
        );

        return array_values(array_filter(array_map(fn ($r) => $r->slug, $rows)));
    }

    /** @return array<int,string> */
    private function assuntosDaPalestra(int $palestraId): array
    {
        $rows = $this->db->select(
            "SELECT t.slug FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'assuntos-principais'",
            [$palestraId]
        );

        return array_values(array_map(fn ($r) => $r->slug, $rows));
    }
}
