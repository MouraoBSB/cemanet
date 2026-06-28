<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Http\Controllers;

use App\Models\Biblioteca;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve a mídia da biblioteca por uma URL estável e portável (/midia/{id}/{conversao}).
 * Restrita à coleção 'biblioteca' (#5); conversões em allowlist (#11); cache ramificado (#1).
 */
class MidiaController extends Controller
{
    public function serve(int $media, string $conversao = 'web'): BinaryFileResponse
    {
        // #5: só mídia da biblioteca é servível por esta rota.
        $m = Media::query()
            ->where('collection_name', Biblioteca::COLECAO)
            ->findOrFail($media);

        // #11: conversão fora da allowlist cai para 'web' (nunca serve original arbitrário por nome).
        $conversao = in_array($conversao, ['web', 'thumb'], true) ? $conversao : 'web';

        $gerada  = $m->hasGeneratedConversion($conversao);
        $caminho = $gerada ? $m->getPath($conversao) : $m->getPath();
        abort_unless(is_file($caminho), 404);

        // #1: immutable só quando a conversão existe (conteúdo estável por media id);
        // fallback (original sob a URL da conversão) usa cache curto p/ pegar a WebP depois.
        $cache = $gerada
            ? 'public, max-age=31536000, immutable'
            : 'public, max-age=60';

        return response()->file($caminho, [
            'Cache-Control' => $cache,
            'Content-Type'  => $gerada ? 'image/webp' : $m->mime_type,
        ]);
    }
}
