<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeitorBlogMysql implements LeitorBlog
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function posts(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_excerpt, post_content, post_status, post_date
             FROM wp_posts
             WHERE post_type = 'post' AND post_status IN ('publish', 'draft', 'future')"
        );

        $out = [];
        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $meta = $this->metasDe($id);

            // imagem destacada
            $thumbId = isset($meta['_thumbnail_id']) && $meta['_thumbnail_id'] !== ''
                ? (int) $meta['_thumbnail_id']
                : null;
            $imagemUrl = $thumbId ? $this->urlDaImagem($thumbId) : null;
            $imagemAlt = $thumbId ? $this->altDaImagem($thumbId) : null;

            // categorias
            $categoriasSlugs = $this->termosDoPost($id, 'category');

            // categoria principal: rank_math_primary_category (term_id) → slug; fallback: 1ª
            $categoriaPrincipalSlug = $this->categoriaPrincipalSlug(
                $meta['rank_math_primary_category'] ?? null,
                $categoriasSlugs
            );

            // tags
            $tags = $this->tagsDoPost($id);

            // SEO
            $seo = [
                'titulo' => ($meta['rank_math_title'] ?? null) ?: ($meta['_yoast_wpseo_title'] ?? null) ?: null,
                'descricao' => ($meta['rank_math_description'] ?? null) ?: ($meta['_yoast_wpseo_metadesc'] ?? null) ?: null,
                'keyword' => ($meta['rank_math_focus_keyword'] ?? null) ?: ($meta['_yoast_wpseo_focuskw'] ?? null) ?: null,
                // O Rank Math às vezes grava aqui um array PHP serializado em vez da URL —
                // só aceita se for uma URL http(s) de verdade (senão cai no fallback da destacada).
                'og_imagem' => preg_match('~^https?://~i', (string) ($meta['rank_math_og_content_image'] ?? ''))
                    ? $meta['rank_math_og_content_image']
                    : null,
            ];

            $out[] = [
                'wp_id' => $id,
                'titulo' => $p->post_title,
                'slug' => $p->post_name,
                'resumo' => ($p->post_excerpt !== '' && $p->post_excerpt !== null) ? $p->post_excerpt : null,
                'conteudo' => $p->post_content,
                'data_publicacao' => Carbon::parse($p->post_date, 'America/Sao_Paulo'),
                'status' => TransformadorBlog::statusPost($p->post_status),
                'imagem_url' => $imagemUrl,
                'imagem_alt' => $imagemAlt,
                'categorias_slugs' => $categoriasSlugs,
                'categoria_principal_slug' => $categoriaPrincipalSlug,
                'tags' => $tags,
                'faqs' => TransformadorBlog::faqsDoRepeater($meta['_faq'] ?? null),
                'galeria' => TransformadorBlog::galeriaDoRepeater($meta['_fotos_carrossel_'] ?? null),
                'seo' => $seo,
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (primeiro valor por chave) */
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

    /** URL do arquivo via guid do attachment (wp_posts do tipo attachment). */
    private function urlDaImagem(int $attId): ?string
    {
        $row = $this->db->selectOne(
            'SELECT guid FROM wp_posts WHERE ID = ? AND post_type = ? LIMIT 1',
            [$attId, 'attachment']
        );

        return $row->guid ?? null;
    }

    /** Alt text do attachment via meta _wp_attachment_image_alt. */
    private function altDaImagem(int $attId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = '_wp_attachment_image_alt' LIMIT 1",
            [$attId]
        );

        $alt = $row->meta_value ?? null;

        return ($alt !== null && $alt !== '') ? $alt : null;
    }

    /**
     * Slugs de termos de uma taxonomia associados a um post.
     *
     * @return array<int,string>
     */
    private function termosDoPost(int $postId, string $taxonomy): array
    {
        $rows = $this->db->select(
            'SELECT t.slug FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = ?',
            [$postId, $taxonomy]
        );

        return array_values(array_map(fn ($r) => $r->slug, $rows));
    }

    /**
     * Tags do post: array de ['nome' => ..., 'slug' => ...].
     *
     * @return array<int,array{nome:string,slug:string}>
     */
    private function tagsDoPost(int $postId): array
    {
        $rows = $this->db->select(
            "SELECT t.name, t.slug FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'post_tag'",
            [$postId]
        );

        return array_values(array_map(fn ($r) => ['nome' => $r->name, 'slug' => $r->slug], $rows));
    }

    /**
     * Resolve o slug da categoria principal.
     * Usa rank_math_primary_category (term_id) → slug do banco; fallback: 1ª categoria.
     *
     * @param  array<int,string>  $categoriasSlugs
     */
    private function categoriaPrincipalSlug(?string $rankMathTermId, array $categoriasSlugs): ?string
    {
        if ($rankMathTermId !== null && $rankMathTermId !== '') {
            $row = $this->db->selectOne(
                'SELECT slug FROM wp_terms WHERE term_id = ? LIMIT 1',
                [(int) $rankMathTermId]
            );
            if ($row !== null) {
                return $row->slug;
            }
        }

        return $categoriasSlugs[0] ?? null;
    }
}
