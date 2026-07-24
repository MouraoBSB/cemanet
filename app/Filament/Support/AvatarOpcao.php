<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace App\Filament\Support;

/**
 * HTML de uma opção de Select: avatar circular + fallback de iniciais.
 * Idioma visual do single da mensagem (resources/views/mensagens/show.blade.php:61-64).
 * Estilo INLINE de propósito: o dropdown do Filament injeta este HTML por innerHTML,
 * fora do bundle do site — classe utilitária do site pode não existir ali (O4).
 * `e()` no nome E na URL: allowHtml não escapa (O2).
 */
class AvatarOpcao
{
    public static function html(?string $fotoUrl, string $nome, string $iniciais): string
    {
        $circulo = $fotoUrl !== null
            ? '<img src="'.e($fotoUrl).'" alt="" style="width:1.75rem;height:1.75rem;border-radius:9999px;object-fit:cover;flex-shrink:0;">'
            : '<span aria-hidden="true" style="display:inline-grid;place-items:center;width:1.75rem;height:1.75rem;border-radius:9999px;background-image:linear-gradient(to bottom right,#f2a81e,#d98a14);font-size:10px;font-weight:600;color:#3a3266;flex-shrink:0;">'.e($iniciais).'</span>';

        return '<span style="display:inline-flex;align-items:center;gap:0.5rem;">'.$circulo.'<span>'.e($nome).'</span></span>';
    }
}
