<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Support\Blog;

interface FonteReflexao
{
    /** Retorna a reflexão do dia, ou null se não houver. */
    public function doDia(): ?string;
}
