<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Palestrante extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const COLECAO_FOTO = 'foto';

    protected $fillable = [
        'nome', 'slug', 'foto', 'bio', 'email', 'telefone',
        'mostrar_email', 'mostrar_telefone', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'mostrar_email' => 'boolean',
            'mostrar_telefone' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function palestras(): BelongsToMany
    {
        return $this->belongsToMany(Palestra::class, 'palestra_pessoa', 'pessoa_id', 'palestra_id')
            ->withPivot('papel')
            ->withTimestamps();
    }

    public function palestrasMinistradas(): BelongsToMany
    {
        return $this->palestras()->wherePivot('papel', Palestra::PAPEL_PALESTRANTE);
    }

    public function registerMediaCollections(): void
    {
        // Foto do palestrante no mesmo pipeline do blog: WebP otimizada + miniatura,
        // disco public, conversões síncronas. Cap do original ≤2000px herda do CaparOriginalDaMidia.
        $this->addMediaCollection(self::COLECAO_FOTO)
            ->singleFile()
            ->useDisk('public')
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('web')
                    ->fit(Fit::Max, 1200, 1200)
                    ->format('webp')
                    ->quality(82)
                    ->nonQueued();
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 400, 400)
                    ->format('webp')
                    ->nonQueued();
            });
    }

    /** URL da foto (WebP web) via Media Library, ou null. */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'web') ?: null,
        );
    }

    /** URL da miniatura (WebP thumb) via Media Library, ou null. */
    protected function fotoThumbUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'thumb') ?: null,
        );
    }

    protected function bio(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }
}
