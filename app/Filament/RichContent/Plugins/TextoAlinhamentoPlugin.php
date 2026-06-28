<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\Plugins;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Support\Facades\FilamentAsset;

/**
 * Plugin RichContent: substitui a extensão `textAlign` padrão do Filament por uma
 * versão que emite CLASSES has-text-align-* (em vez de `style` inline). As tools de
 * alinhamento de texto são as NATIVAS do Filament (alignStart/Center/End/Justify),
 * apenas listadas no toolbarButtons do PostResource.
 */
class TextoAlinhamentoPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /** @return array<\Tiptap\Core\Extension> */
    public function getTipTapPhpExtensions(): array
    {
        // Não é necessário espelho PHP: o conteúdo do blog é salvo como o HTML do
        // editor (classes) e sanitizado pelo mutator; o front renderiza o HTML cru.
        return [];
    }

    /** @return array<string> */
    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc('texto-alinhado', 'app')];
    }

    /** @return array<\Filament\Forms\Components\RichEditor\RichEditorTool> */
    public function getEditorTools(): array
    {
        return [];
    }

    /** @return array<\Filament\Actions\Action> */
    public function getEditorActions(): array
    {
        return [];
    }
}
