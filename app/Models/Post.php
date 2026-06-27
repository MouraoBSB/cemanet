<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGENDADO = 'agendado';

    // Coleções de mídia
    public const COLECAO_DESTACADA = 'destacada';

    public const COLECAO_GALERIA = 'galeria';

    public const COLECAO_OG = 'og';

    /** Registrado na Task 4 (rich content); definido aqui para uso antecipado. */
    public const COLECAO_CONTEUDO = 'conteudo';

    protected $fillable = [
        'titulo',
        'slug',
        'resumo',
        'conteudo',
        'imagem_destacada',
        'imagem_destacada_alt',
        'criado_por_id',
        'categoria_principal_id',
        'destaque',
        'tempo_leitura_min',
        'visualizacoes',
        'data_publicacao',
        'status',
        'wp_id',
        'seo_titulo',
        'seo_descricao',
        'seo_keyword',
        'og_imagem',
        'robots_noindex',
        'canonical',
    ];

    protected function casts(): array
    {
        return [
            'data_publicacao' => 'datetime',
            'destaque' => 'boolean',
            'robots_noindex' => 'boolean',
            'visualizacoes' => 'integer',
            'tempo_leitura_min' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Imagem de capa do post — arquivo único
        $this->addMediaCollection(self::COLECAO_DESTACADA)
            ->singleFile()
            ->registerMediaConversions(function (Media $media) {
                // Versão web otimizada com srcset responsivo
                $this->addMediaConversion('web')
                    ->fit(Fit::Max, 1920, 1920)
                    ->format('webp')
                    ->quality(82)
                    ->withResponsiveImages()
                    ->nonQueued();
                // Miniatura para listagens
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 400, 300)
                    ->format('webp')
                    ->queued();
                // OG fallback a partir da capa
                $this->addMediaConversion('og')
                    ->fit(Fit::Crop, 1200, 630)
                    ->format('jpg')
                    ->quality(85)
                    ->nonQueued();
            });

        // Galeria de fotos do post — múltiplos arquivos, ordenáveis
        $this->addMediaCollection(self::COLECAO_GALERIA)
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('web')
                    ->fit(Fit::Max, 1920, 1920)
                    ->format('webp')
                    ->quality(82)
                    ->withResponsiveImages()
                    ->nonQueued();
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 400, 300)
                    ->format('webp')
                    ->queued();
            });

        // Imagem OG personalizada — arquivo único, gerada em 1200×630
        $this->addMediaCollection(self::COLECAO_OG)
            ->singleFile()
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('og')
                    ->fit(Fit::Crop, 1200, 630)
                    ->format('jpg')
                    ->quality(85)
                    ->nonQueued();
            });
    }

    /**
     * URL da conversão 'web' da imagem destacada (WebP otimizado).
     * Retorna null quando nenhuma mídia foi anexada à coleção.
     */
    public function getImagemDestacadaUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::COLECAO_DESTACADA, 'web') ?: null;
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLICADO)
            ->where('data_publicacao', '<=', now());
    }

    public function scopeMaisLidas(Builder $query): Builder
    {
        return $query->publicado()->orderByDesc('visualizacoes');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(Categoria::class, 'categoria_post');
    }

    public function categoriaPrincipal(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_principal_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(PostFaq::class)->orderBy('ordem');
    }

    public function imagens(): HasMany
    {
        return $this->hasMany(PostImagem::class)->orderBy('ordem');
    }

    public function getUrlPublicaAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    public function getCorCategoriaAttribute(): string
    {
        return $this->categoriaPrincipal?->cor ?? '#7A8A8A';
    }

    protected function conteudo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo_blog') : null,
        );
    }
}
