<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Filament\RichContent\Plugins;

use App\Filament\RichContent\Actions\InserirDaBibliotecaAction;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;

/**
 * Plugin RichContent: adiciona a ferramenta "Inserir da biblioteca", que abre um modal
 * com a grade de miniaturas da biblioteca e insere a imagem escolhida como
 * <img src="/midia/{id}/web" alt="..."> (URL portável) na posição do cursor.
 */
class BibliotecaMidiaPlugin implements RichContentPlugin
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
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        // Extensão que intercepta colar/arrastar de imagem no corpo, envia para a
        // biblioteca (/admin/midia/colar) e insere a URL portável /midia/{id}/web.
        return [FilamentAsset::getScriptSrc('colar-na-biblioteca', 'app')];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('inserirDaBiblioteca')
                ->label('Inserir da biblioteca')
                ->icon(Heroicon::OutlinedPhoto)
                ->action('inserirDaBiblioteca'),
        ];
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    public function getEditorActions(): array
    {
        return [
            InserirDaBibliotecaAction::make(),
        ];
    }
}
