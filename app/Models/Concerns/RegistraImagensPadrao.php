<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Tratamento PADRÃO de imagem do sistema, reutilizável por qualquer model HasMedia
 * (palestrantes, eventos, e o que vier). Registra uma coleção no disco `public` com
 * WebP otimizada (conversão `web`) + miniatura quadrada (`thumb`), ambas síncronas.
 * O cap do original ≤2000px herda do listener CaparOriginalDaMidia.
 *
 * Uso no model:
 *   class Evento extends Model implements HasMedia {
 *       use InteractsWithMedia, RegistraImagensPadrao;
 *       public function registerMediaCollections(): void {
 *           $this->registrarColecaoImagem('galeria', unica: false, larguraWeb: 1920);
 *       }
 *   }
 */
trait RegistraImagensPadrao
{
    /**
     * @param  string  $colecao  nome da coleção de mídia
     * @param  bool  $unica  true = singleFile (1 imagem); false = múltiplas (galeria)
     * @param  int  $larguraWeb  largura máxima da conversão `web` (px)
     * @param  int  $ladoThumb  lado da miniatura quadrada `thumb` (px)
     */
    protected function registrarColecaoImagem(
        string $colecao,
        bool $unica = true,
        int $larguraWeb = 1600,
        int $ladoThumb = 400,
    ): void {
        $config = $this->addMediaCollection($colecao)->useDisk('public');

        if ($unica) {
            $config->singleFile();
        }

        $config->registerMediaConversions(function (Media $media) use ($larguraWeb, $ladoThumb) {
            $this->addMediaConversion('web')
                ->fit(Fit::Max, $larguraWeb, $larguraWeb)
                ->format('webp')
                ->quality(82)
                ->nonQueued();

            $this->addMediaConversion('thumb')
                ->fit(Fit::Crop, $ladoThumb, $ladoThumb)
                ->format('webp')
                ->nonQueued();
        });
    }
}
