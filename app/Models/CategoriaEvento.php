<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaEvento extends Model
{
    protected $table = 'categorias_evento';

    protected $fillable = ['nome', 'slug', 'cor', 'cor_texto', 'icone', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'ordem' => 'integer'];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
