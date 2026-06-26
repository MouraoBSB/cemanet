<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostFaq extends Model
{
    protected $table = 'post_faqs';

    protected $fillable = [
        'post_id',
        'pergunta',
        'resposta',
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
