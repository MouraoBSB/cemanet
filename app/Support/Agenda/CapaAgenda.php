<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Support\Agenda;

use App\Models\ConfiguracaoAgenda;

class CapaAgenda
{
    /** Resolve a capa da Agenda (configurada no admin) para uma URL pública WebP, ou null se não houver. */
    public static function url(): ?string
    {
        return ConfiguracaoAgenda::instance()->capaUrl;
    }
}
