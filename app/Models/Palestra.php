<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use App\Support\Palestras\LinkDrive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'online', 'link_youtube', 'slide', 'duracao', 'referencias_evangelicas',
        'cor_fundo', 'publico_online', 'publico_presencial', 'publico_total', 'curtidas', 'status',
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

    public function referencias(): HasMany
    {
        return $this->hasMany(PalestraReferencia::class)->orderBy('ordem');
    }

    public function getYoutubeIdAttribute(): ?string
    {
        if ($this->link_youtube && preg_match('~(?:v=|youtu\.be/|live/|embed/|shorts/)([A-Za-z0-9_-]{6,})~', $this->link_youtube, $m)) {
            return $m[1];
        }

        return null;
    }

    public function getYoutubeThumbAttribute(): ?string
    {
        return $this->youtube_id ? "https://i.ytimg.com/vi/{$this->youtube_id}/mqdefault.jpg" : null;
    }

    /** Thumb maior (hqdefault) para Open Graph / social cards. */
    public function getYoutubeThumbHqAttribute(): ?string
    {
        return $this->youtube_id ? "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg" : null;
    }

    /** Link de download direto do slide (derivado do link cru), ou null. */
    protected function slideDownloadUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => LinkDrive::paraDownload($this->slide));
    }

    protected function descricao(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }
}
