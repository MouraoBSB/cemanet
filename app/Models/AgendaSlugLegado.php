<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaSlugLegado extends Model
{
    use HasFactory;

    protected $table = 'agenda_slugs_legado';

    // A tabela não tem created_at/updated_at (mapa raso de 301).
    public $timestamps = false;

    protected $fillable = ['slug', 'data'];

    protected function casts(): array
    {
        return [
            // Formato explícito: cast 'date' puro serializa no INSERT com o
            // formato de datetime da grammar (Y-m-d H:i:s). No SQLite (usado
            // nos testes) a coluna não trunca a hora como o MySQL faz numa
            // coluna DATE nativa, quebrando comparações por string. Fixando
            // o formato aqui, a gravação já sai como Y-m-d nos dois motores.
            'data' => 'date:Y-m-d',
        ];
    }
}
