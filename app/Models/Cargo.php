<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cargo extends Model
{
    protected $fillable = ['departamento_id', 'nome', 'slug', 'institucional', 'ativo'];

    protected function casts(): array
    {
        return ['institucional' => 'boolean', 'ativo' => 'boolean'];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cargo_usuario')->withTimestamps();
    }
}
