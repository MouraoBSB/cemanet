<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Palestra extends Model
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';
    public const STATUS_RASCUNHO = 'rascunho';

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
}
