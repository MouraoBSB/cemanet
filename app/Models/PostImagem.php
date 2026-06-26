<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
