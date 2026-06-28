<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pool central de mídia reutilizável do blog. Singleton: existe um único registro
 * ('principal') que é dono da coleção 'biblioteca'. Imagens inseridas no corpo dos
 * posts passam a referenciar esta mídia por URL estável (/midia/{id}/web).
 */
class Biblioteca extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const COLECAO = 'biblioteca';

    protected $guarded = [];

    /** Recupera (ou cria) o único registro da biblioteca. */
    public static function instance(): self
    {
        return static::firstOrCreate(['tipo' => 'principal']);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLECAO)
            ->useDisk('public')
            ->registerMediaConversions(function (Media $media) {
                // WebP otimizada servida no corpo dos posts (síncrona — existe ao servir).
                $this->addMediaConversion('web')
                    ->fit(Fit::Max, 1920, 1920)
                    ->format('webp')
                    ->quality(82)
                    ->nonQueued();
                // Miniatura para a grade da biblioteca / preview.
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 400, 300)
                    ->format('webp')
                    ->nonQueued();
            });
    }
}
