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
     * Garante que o original armazenado não ultrapasse o teto de cada coleção,
     * no LADO MAIS LONGO (largura OU altura — pega imagens retrato também):
     *   - coleção 'og'  → ≤ 1200 px
     *   - demais        → ≤ 2000 px
     *
     * Redimensiona in-place preservando proporção (Fit::Max cabe a imagem
     * dentro da caixa teto×teto) e atualiza o campo `size` no registro de mídia
     * para refletir o novo tamanho em disco.
     */
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;
        $teto = $media->collection_name === Post::COLECAO_OG ? 1200 : 2000;
        $caminho = $media->getPath(); // caminho do ORIGINAL no disco

        $dim = @getimagesize($caminho);

        if ($dim === false || max($dim[0], $dim[1]) <= $teto) {
            return; // não é imagem ou já cabe no teto (largura e altura)
        }

        // Fit::Max limita largura E altura ao teto, preservando proporção e sem ampliar.
        Image::load($caminho)->fit(Fit::Max, $teto, $teto)->save();

        $media->size = filesize($caminho);
        $media->saveQuietly();
    }
}
