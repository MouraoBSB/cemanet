<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'slug',
        'resumo',
        'conteudo',
        'imagem_destacada',
        'imagem_destacada_alt',
        'criado_por_id',
        'categoria_principal_id',
        'destaque',
        'tempo_leitura_min',
        'visualizacoes',
        'data_publicacao',
        'status',
        'wp_id',
        'seo_titulo',
        'seo_descricao',
        'seo_keyword',
        'og_imagem',
        'robots_noindex',
        'canonical',
    ];
}
