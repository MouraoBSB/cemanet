<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Listeners;

use App\Models\Biblioteca;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Calcula o SHA-256 do arquivo (já capado) e guarda em custom_properties['sha256'],
 * só para a coleção 'biblioteca'. Base do dedup. DEVE rodar APÓS CaparOriginalDaMidia
 * (registrado depois no AppServiceProvider) para refletir os bytes finais.
 */
class CalcularHashMidia
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if ($media->collection_name !== Biblioteca::COLECAO) {
            return;
        }

        $media->setCustomProperty('sha256', hash_file('sha256', $media->getPath()))
            ->saveQuietly();
    }
}
