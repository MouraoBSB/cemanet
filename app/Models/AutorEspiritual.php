<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use App\Models\Concerns\TemIniciais;
use App\Models\Contracts\TemDepartamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AutorEspiritual extends Model implements HasMedia, TemDepartamento
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao, TemIniciais;

    // Pluralização pt-BR: o pluralizador do Laravel geraria 'autor_espirituals'.
    protected $table = 'autores_espirituais';

    public const COLECAO_FOTO = 'foto';

    protected $fillable = ['nome', 'slug', 'chamada', 'bio', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_autor_espiritual', 'autor_espiritual_id', 'departamento_id');
    }

    public function registerMediaCollections(): void
    {
        // Tratamento padrão de imagem (trait RegistraImagensPadrao): disco public, WebP web + miniatura.
        $this->registrarColecaoImagem(self::COLECAO_FOTO);
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
