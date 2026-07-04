<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Atributo extends Model
{
    protected $fillable = ['nome', 'slug', 'descricao'];

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'atributo_usuario')
            ->withPivot('desde', 'ate')->withTimestamps();
    }
}
