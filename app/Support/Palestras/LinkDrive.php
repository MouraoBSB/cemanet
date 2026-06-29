<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Support\Palestras;

class LinkDrive
{
    /** Converte um link do Google Drive em link de download direto. Não-Drive fica intacto. */
    public static function paraDownload(?string $link): ?string
    {
        if ($link === null || trim($link) === '') {
            return null;
        }

        $link = trim(html_entity_decode($link, ENT_QUOTES | ENT_HTML5));
        $host = parse_url($link, PHP_URL_HOST) ?: '';

        // "É Drive?" decide pelo host — nunca tentamos extrair ID de outro host.
        if (! str_contains($host, 'drive.google.com')) {
            return $link;
        }

        // Pasta não baixa via uc?export=download.
        if (str_contains($link, '/drive/folders/')) {
            return $link;
        }

        $id = self::extrairId($link);

        return $id !== null
            ? "https://drive.google.com/uc?export=download&id={$id}"
            : $link;
    }

    private static function extrairId(string $link): ?string
    {
        if (preg_match('/[?&]id=([A-Za-z0-9_-]{10,})/', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#/file/d/([A-Za-z0-9_-]{10,})#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('/([A-Za-z0-9_-]{25,})/', $link, $m)) {
            return $m[1];
        }

        return null;
    }
}
