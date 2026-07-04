<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilMembro extends Model
{
    protected $table = 'perfis_membro';

    protected $fillable = [
        'user_id', 'whatsapp', 'whatsapp_publico', 'data_nascimento', 'endereco', 'foto_perfil',
    ];

    protected function casts(): array
    {
        return ['whatsapp_publico' => 'boolean', 'data_nascimento' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
