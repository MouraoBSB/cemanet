<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Support\Agenda;

use App\Models\Configuracao;
use Illuminate\Support\Facades\Storage;

class CapaAgenda
{
    /** Resolve a capa da Agenda (configurada no admin) para uma URL pública, ou null se não houver. */
    public static function url(): ?string
    {
        $caminho = Configuracao::valor('agenda_capa');

        return filled($caminho) ? Storage::disk('public')->url($caminho) : null;
    }
}
