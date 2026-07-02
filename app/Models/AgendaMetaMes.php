<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaMetaMes extends Model
{
    use HasFactory;

    protected $table = 'agenda_metas_mes';

    protected $fillable = ['ano', 'mes', 'titulo'];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
        ];
    }
}
