<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoRealizado extends Model
{
    protected $table = 'cursos_realizados';

    protected $fillable = ['user_id', 'nome', 'ano', 'local', 'ordem'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
