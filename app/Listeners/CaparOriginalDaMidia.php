<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Listeners;

use App\Models\Post;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class CaparOriginalDaMidia
{
    /**
     * Reencoda o original recém-adicionado para o padrão único do site:
     *   1. capa o LADO MAIS LONGO (largura OU altura — pega retrato também):
     *      coleção 'og' → ≤ 1200 px; demais → ≤ 2000 px (Fit::Max, sem ampliar);
     *   2. converte para **WebP** — o disco guarda SÓ WebP, nunca um original
     *      "gordo" JPEG/PNG. As conversões (`web`/`thumb`) são geradas DEPOIS
     *      deste evento, já a partir do WebP.
     *
     * Renomeia o arquivo em disco para `.webp` e atualiza `file_name`/`mime_type`/
     * `size` no registro de mídia (o path das conversões deriva do novo `file_name`).
     * Arquivos não-raster (ex.: SVG) passam intactos.
     */
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;
        $caminho = $media->getPath(); // caminho do ORIGINAL no disco

        if (@getimagesize($caminho) === false) {
            return; // não é raster (SVG etc.) — não mexe
        }

        $teto = $media->collection_name === Post::COLECAO_OG ? 1200 : 2000;
        $novoNome = pathinfo((string) $media->file_name, PATHINFO_FILENAME).'.webp';
        $novoCaminho = dirname($caminho).DIRECTORY_SEPARATOR.$novoNome;

        // Fit::Max limita largura E altura ao teto (proporção preservada, sem ampliar);
        // o formato WebP é inferido pela extensão `.webp` do caminho de destino.
        Image::load($caminho)
            ->fit(Fit::Max, $teto, $teto)
            ->quality(85)
            ->save($novoCaminho);

        if ($novoCaminho !== $caminho) {
            @unlink($caminho); // remove o original no formato antigo
        }

        $media->file_name = $novoNome;
        $media->mime_type = 'image/webp';
        $media->size = filesize($novoCaminho);
        $media->saveQuietly();
    }
}
