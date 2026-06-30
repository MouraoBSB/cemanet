<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalestraReferencia extends Model
{
    protected $fillable = ['obra', 'autor', 'nota', 'ordem'];

    public function palestra(): BelongsTo
    {
        return $this->belongsTo(Palestra::class);
    }
}
