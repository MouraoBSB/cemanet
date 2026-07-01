<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Palestrante extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    public const COLECAO_FOTO = 'foto';

    protected $fillable = [
        'nome', 'slug', 'bio', 'email', 'telefone',
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
        // Tratamento padrão de imagem do sistema (trait RegistraImagensPadrao):
        // disco public, WebP web + miniatura, síncrono. Reutilizável por eventos etc.
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

    /** Iniciais (1ª letra das 2 primeiras palavras do nome), maiúsculas — fallback do avatar. */
    protected function iniciais(): Attribute
    {
        return Attribute::get(function (): string {
            $palavras = preg_split('/\s+/', trim((string) $this->nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $letras = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palavras, 0, 2));

            return $letras === [] ? '?' : implode('', $letras);
        });
    }

    protected function bio(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }
}
