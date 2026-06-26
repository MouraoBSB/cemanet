<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostFaq extends Model
{
    protected $table = 'post_faqs';

    protected $fillable = [
        'post_id',
        'pergunta',
        'resposta',
        'ordem',
    ];
}
