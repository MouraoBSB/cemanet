<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Mensagens;

use App\Models\Mensagem;
use Illuminate\Support\Str;

/**
 * Gera slug único de Mensagem no servidor — o lançamento pelo médium (fora do /admin) não tem
 * campo de slug na tela.
 */
class SlugMensagem
{
    /**
     * Str::slug do título + sufixo numérico incremental até não colidir com `mensagens.slug`
     * (coluna única). $ignorarId exclui o próprio registro da checagem (reedição).
     */
    public static function unico(string $titulo, ?int $ignorarId = null): string
    {
        $base = Str::slug($titulo);
        $slug = $base;
        $sufixo = 2;

        while (
            Mensagem::query()
                ->where('slug', $slug)
                ->when($ignorarId !== null, fn ($query) => $query->whereKeyNot($ignorarId))
                ->exists()
        ) {
            $slug = "{$base}-{$sufixo}";
            $sufixo++;
        }

        return $slug;
    }
}
