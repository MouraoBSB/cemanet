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
            $dados = unserialize($serial);
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
                'ordem'    => $ordem++,
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
            $dados = unserialize($serial);
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
                'url'   => (string) $item['url'],
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

        $palavras = str_word_count(strip_tags($html));

        return max(1, (int) ceil($palavras / 200));
    }

    /**
     * Converte o status do WordPress para o status interno do sistema.
     */
    public static function statusPost(string $wp): string
    {
        return match ($wp) {
            'publish' => 'publicado',
            'future'  => 'agendado',
            default   => 'rascunho',
        };
    }
}
