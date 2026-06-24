<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Palestra extends Model
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const PAPEL_PALESTRANTE = 'palestrante';

    public const PAPEL_DIRETOR = 'diretor';

    protected $fillable = [
        'titulo', 'slug', 'subtitulo', 'resumo', 'descricao', 'data_da_palestra',
        'online', 'link_youtube', 'cor_fundo', 'publico_online', 'publico_presencial',
        'publico_total', 'status',
    ];

    protected function casts(): array
    {
        return [
            'data_da_palestra' => 'datetime',
            'online' => 'boolean',
            'publico_online' => 'integer',
            'publico_presencial' => 'integer',
            'publico_total' => 'integer',
        ];
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    public function palestrantes(): BelongsToMany
    {
        return $this->belongsToMany(Palestrante::class, 'palestra_pessoa', 'palestra_id', 'pessoa_id')
            ->withPivot('papel')
            ->withTimestamps();
    }

    public function palestrantesAtivos(): BelongsToMany
    {
        return $this->palestrantes()
            ->wherePivot('papel', self::PAPEL_PALESTRANTE)
            ->where('palestrantes.ativo', true);
    }

    public function assuntos(): BelongsToMany
    {
        return $this->belongsToMany(Assunto::class, 'assunto_palestra', 'palestra_id', 'assunto_id');
    }

    public function getDiretorAttribute(): ?Palestrante
    {
        return $this->relationLoaded('palestrantes')
            ? $this->palestrantes->firstWhere('pivot.papel', self::PAPEL_DIRETOR)
            : $this->palestrantes()->wherePivot('papel', self::PAPEL_DIRETOR)->first();
    }

    public function destaques(): HasMany
    {
        return $this->hasMany(PalestraDestaque::class)->orderBy('ordem');
    }
}
