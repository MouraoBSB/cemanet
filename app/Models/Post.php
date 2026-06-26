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

class Post extends Model
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGENDADO = 'agendado';

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
