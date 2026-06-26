<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Support\Blog;

use App\Models\Configuracao;

class ReflexaoConfig implements FonteReflexao
{
    public function doDia(): ?string
    {
        $valor = Configuracao::valor('blog.reflexao_do_dia');

        return filled($valor) ? (string) $valor : null;
    }
}
