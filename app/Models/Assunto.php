<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assunto extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'slug', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Assunto::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Assunto::class, 'parent_id');
    }
}
