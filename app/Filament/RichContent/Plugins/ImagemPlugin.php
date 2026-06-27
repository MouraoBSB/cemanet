<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\Plugins;

use App\Filament\RichContent\TipTap\ImagemAtributosExtension;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;

/**
 * Plugin RichContent: adiciona alinhamento e tamanho de imagem via classes WP.
 * Registra a extensão PHP (espelho candidato B do JS) e as 6 ferramentas de toolbar.
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
        return [app(ImagemAtributosExtension::class)];
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

            RichEditorTool::make('imagemAlinharCentro')
                ->icon(Heroicon::Bars3)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("center").run()'),

            RichEditorTool::make('imagemAlinharDireita')
                ->icon(Heroicon::Bars3BottomRight)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("right").run()'),

            RichEditorTool::make('imagemTamanhoMedio')
                ->icon(Heroicon::Photo)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("medium").run()'),

            RichEditorTool::make('imagemTamanhoGrande')
                ->icon(Heroicon::ArrowsPointingOut)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("large").run()'),

            RichEditorTool::make('imagemTamanhoTotal')
                ->icon(Heroicon::ArrowsPointingOut)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("full").run()'),
        ];
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
