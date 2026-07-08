<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Models;

use App\Enums\VisibilidadeEvento;
use App\Models\Concerns\RegistraImagensPadrao;
use App\Support\Eventos\PeriodoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Evento extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const COLECAO_FLYER = 'flyer';

    public const COLECAO_GALERIA = 'galeria';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'conteudo',
        'data_inicio', 'hora_inicio', 'data_fim', 'hora_fim',
        'local', 'categoria_evento_id', 'visibilidade', 'status', 'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'visibilidade' => VisibilidadeEvento::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        // Flyer/capa (1 imagem) + galeria (N imagens), tratamento padrão do sistema.
        $this->registrarColecaoImagem(self::COLECAO_FLYER);
        $this->registrarColecaoImagem(self::COLECAO_GALERIA, unica: false, larguraWeb: 1920);
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEvento::class, 'categoria_evento_id');
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_evento', 'evento_id', 'departamento_id');
    }

    /** data_inicio: string Y-m-d na escrita (portável), Carbon na leitura. */
    protected function dataInicio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function dataFim(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function horaInicio(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function conteudo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function resumo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    /** Período por extenso (via classe pura). Usa os valores crus Y-m-d. */
    public function getPeriodoAttribute(): string
    {
        $inicio = $this->attributes['data_inicio'] ?? null;
        if ($inicio === null) {
            return '';
        }

        return PeriodoEvento::formata($inicio, $this->hora_inicio, $this->attributes['data_fim'] ?? null, $this->hora_fim);
    }

    /** URL do flyer (WebP web) via Media Library, ou null. */
    protected function flyerUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FLYER, 'web') ?: null);
    }

    /** Normaliza hora para 'HH:MM' zero-padded; aceita 'H:i' ou 'H:i:s'. Inválido passa cru p/ validação acusar. */
    private static function normalizaHora(?string $v): ?string
    {
        if ($v === null || trim($v) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($v), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return trim($v);
    }
}
