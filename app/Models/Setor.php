<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Setor extends Model
{
    protected $table = 'setores';

    protected $fillable = ['departamento_id', 'nome', 'slug', 'provisorio', 'ativo'];

    protected function casts(): array
    {
        return ['provisorio' => 'boolean', 'ativo' => 'boolean'];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'setor_usuario')
            ->withPivot('funcao', 'desde')->withTimestamps();
    }
}
