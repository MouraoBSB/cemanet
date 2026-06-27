<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Listeners;

use App\Models\Post;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class CaparOriginalDaMidia
{
    /**
     * Garante que o original armazenado não ultrapasse o teto de cada coleção:
     *   - coleção 'og'  → ≤ 1200 px de largura
     *   - demais        → ≤ 2000 px de largura
     *
     * Redimensiona in-place preservando proporção e atualiza o campo `size`
     * no registro de mídia para refletir o novo tamanho em disco.
     */
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;
        $teto = $media->collection_name === Post::COLECAO_OG ? 1200 : 2000;
        $caminho = $media->getPath(); // caminho do ORIGINAL no disco

        $dim = @getimagesize($caminho);

        if ($dim === false || $dim[0] <= $teto) {
            return; // não é imagem ou já está dentro do teto
        }

        Image::load($caminho)->width($teto)->save(); // preserva proporção

        $media->size = filesize($caminho);
        $media->saveQuietly();
    }
}
