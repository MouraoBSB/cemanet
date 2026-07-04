<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $fillable = ['sigla', 'nome', 'slug', 'descricao', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }
}
