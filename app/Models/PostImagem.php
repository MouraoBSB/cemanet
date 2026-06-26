<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostImagem extends Model
{
    protected $table = 'post_imagens';

    protected $fillable = [
        'post_id',
        'caminho',
        'url_legado',
        'alt',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
