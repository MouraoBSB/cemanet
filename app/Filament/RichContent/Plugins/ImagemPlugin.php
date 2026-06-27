<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\Plugins;

use App\Filament\RichContent\TipTap\ImagemExtension;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;

/**
 * Plugin RichContent: adiciona alinhamento de imagem via classes WP.
 * Registra a extensão PHP (espelho do JS) e a ferramenta de toolbar.
 */
class ImagemPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<\Tiptap\Core\Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [app(ImagemExtension::class)];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc('imagem-alinhada', 'app')];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('imagemAlinharEsquerda')
                ->icon(Heroicon::Bars3BottomLeft)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("left").run()'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
