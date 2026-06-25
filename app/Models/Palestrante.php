<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Palestrante extends Model
{
    use HasFactory;

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

    protected function bio(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }
}
