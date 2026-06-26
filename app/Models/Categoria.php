<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'slug',
        'cor',
        'descricao',
        'ordem',
        'wp_term_id',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'categoria_post');
    }

    public function scopeComPostsPublicados(Builder $query): Builder
    {
        return $query->whereHas('posts', fn (Builder $q) => $q->publicado());
    }
}
