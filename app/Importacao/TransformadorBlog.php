<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

class TransformadorBlog
{
    /**
     * Desserializa o repeater de FAQs do JetEngine e retorna array normalizado.
     * Itens sem '_pergunta_faq' ou '_resposta_faq' são ignorados.
     *
     * @return list<array{pergunta: string, resposta: string, ordem: int}>
     */
    public static function faqsDoRepeater(?string $serial): array
    {
        if (empty($serial)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serial, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if (! is_array($dados)) {
            return [];
        }

        $faqs = [];
        $ordem = 0;
        foreach ($dados as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! array_key_exists('_pergunta_faq', $item) || ! array_key_exists('_resposta_faq', $item)) {
                continue;
            }
            $faqs[] = [
                'pergunta' => (string) $item['_pergunta_faq'],
                'resposta' => (string) $item['_resposta_faq'],
                'ordem' => $ordem++,
            ];
        }

        return $faqs;
    }

    /**
     * Desserializa o repeater de galeria do JetEngine e retorna array normalizado.
     * Itens sem 'id' ou 'url' são ignorados.
     *
     * @return list<array{url: string, wp_id: int, ordem: int}>
     */
    public static function galeriaDoRepeater(?string $serial): array
    {
        if (empty($serial)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serial, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if (! is_array($dados)) {
            return [];
        }

        $galeria = [];
        $ordem = 0;
        foreach ($dados as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! array_key_exists('id', $item) || ! array_key_exists('url', $item)) {
                continue;
            }
            $galeria[] = [
                'url' => (string) $item['url'],
                'wp_id' => (int) $item['id'],
                'ordem' => $ordem++,
            ];
        }

        return $galeria;
    }

    /**
     * Estima o tempo de leitura em minutos (mínimo 1) com base em 200 palavras/min.
     */
    public static function tempoLeitura(?string $html): int
    {
        if (empty($html)) {
            return 1;
        }

        $n = preg_match_all('/\pL+/u', strip_tags($html), $m);
        $palavras = $n ?: 0;

        return max(1, (int) ceil($palavras / 200));
    }

    /**
     * Remove resíduos do editor Gutenberg do HTML e converte blocos de colunas
     * para as classes internas do sistema (`colunas` / `coluna`).
     *
     * Ordem de processamento:
     *   1. Remove comentários <!-- wp:… --> e <!-- /wp:… -->
     *   2. Remove tokens de classe `jet-sm-gb-*`
     *   3. Converte <div class="wp-block-columns …"> → <div class="colunas">
     *   4. Converte <div class="wp-block-column …" style="flex-basis:…"> → <div class="coluna">
     *
     * Figuras/imagens (wp-block-image, size-*, aligncenter) não são tocadas.
     */
    public static function limparGutenberg(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Remove comentários Gutenberg
        $html = preg_replace('~<!--\s*/?wp:.*?-->~s', '', $html);

        // 2. Remove tokens de classe jet-sm-gb-* dos atributos class
        $html = preg_replace('~\bjet-sm-gb-\S+~', '', $html);

        // 3. Converte container de colunas (plural primeiro para evitar colisão com wp-block-column singular)
        $html = preg_replace('~<div\s+class="[^"]*\bwp-block-columns\b[^"]*"[^>]*>~i', '<div class="colunas">', $html);

        // 4. Converte cada coluna individual (também descarta o style="flex-basis:…" ao reescrever a tag inteira)
        $html = preg_replace('~<div\s+class="[^"]*\bwp-block-column\b[^"]*"[^>]*>~i', '<div class="coluna">', $html);

        return $html;
    }

    /**
     * Converte o status do WordPress para o status interno do sistema.
     */
    public static function statusPost(string $wp): string
    {
        return match ($wp) {
            'publish' => 'publicado',
            'future' => 'agendado',
            default => 'rascunho',
        };
    }
}
