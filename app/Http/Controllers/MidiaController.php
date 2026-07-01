<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Http\Controllers;

use App\Models\Biblioteca;
use App\Support\Biblioteca\RegistraMidiaBiblioteca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve a mídia da biblioteca por uma URL estável e portável (/midia/{id}/{conversao}).
 * Restrita à coleção 'biblioteca' (#5); conversões em allowlist (#11); cache ramificado (#1).
 */
class MidiaController extends Controller
{
    /**
     * Recebe UMA imagem (POST multipart direto), registra na biblioteca (cap + dedup)
     * e devolve a URL relativa portável /midia/{id}/web para a extensão de colar/arrastar.
     */
    public function colar(Request $request): JsonResponse
    {
        // Validação explícita → SEMPRE 422 JSON (a extensão de colar consome JSON;
        // não depende da negociação do header Accept).
        $validator = Validator::make($request->all(), [
            'imagem' => ['required', 'file', 'image', 'max:20480'], // 20 MB (KB) — cabe no PHP (20M/22M)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Arquivo inválido. Envie uma imagem de até 20 MB.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $arquivo = $request->file('imagem');

        $media = app(RegistraMidiaBiblioteca::class)->aPartirDoCaminho(
            $arquivo->getRealPath(),
            $arquivo->getClientOriginalName(),
        );

        return response()->json([
            'id' => $media->id,
            'url' => route('midia.serve', [$media->id, 'web'], false),
        ]);
    }

    public function serve(int $media, string $conversao = 'web'): BinaryFileResponse
    {
        // #5: só mídia da biblioteca é servível por esta rota.
        $m = Media::query()
            ->where('collection_name', Biblioteca::COLECAO)
            ->findOrFail($media);

        // #11: conversão fora da allowlist cai para 'web' (nunca serve original arbitrário por nome).
        $conversao = in_array($conversao, ['web', 'thumb'], true) ? $conversao : 'web';

        $gerada = $m->hasGeneratedConversion($conversao);
        $caminho = $gerada ? $m->getPath($conversao) : $m->getPath();
        abort_unless(is_file($caminho), 404);

        // #1: immutable só quando a conversão existe (conteúdo estável por media id);
        // fallback (original sob a URL da conversão) usa cache curto p/ pegar a WebP depois.
        $cache = $gerada
            ? 'public, max-age=31536000, immutable'
            : 'public, max-age=60';

        return response()->file($caminho, [
            'Cache-Control' => $cache,
            'Content-Type' => $gerada ? 'image/webp' : $m->mime_type,
        ]);
    }
}
