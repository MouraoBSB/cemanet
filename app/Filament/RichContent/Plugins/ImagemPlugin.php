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
        // IMPORTANTE: dentro de jsHandler/activeJsExpression use aspas SIMPLES para as
        // strings JS. O Filament injeta essas expressões num atributo Alpine delimitado por
        // aspas DUPLAS (x-on:click="..."); aspas duplas internas quebram o parse do Alpine
        // ("Invalid or unexpected token") e o botão fica inerte. As tools nativas do Filament
        // seguem o mesmo padrão (ex.: setTextAlign('start')).
        return [
            RichEditorTool::make('imagemAlinharEsquerda')
                ->label('Imagem: alinhar à esquerda')
                ->icon(Heroicon::Bars3BottomLeft)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem(\'left\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { align: \'left\' })'),

            RichEditorTool::make('imagemAlinharCentro')
                ->label('Imagem: alinhar ao centro')
                ->icon(Heroicon::Bars3)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem(\'center\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { align: \'center\' })'),

            RichEditorTool::make('imagemAlinharDireita')
                ->label('Imagem: alinhar à direita')
                ->icon(Heroicon::Bars3BottomRight)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem(\'right\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { align: \'right\' })'),

            RichEditorTool::make('imagemTamanhoMedio')
                ->label('Imagem: tamanho médio')
                ->icon(Heroicon::Photo)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem(\'medium\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { size: \'medium\' })'),

            RichEditorTool::make('imagemTamanhoGrande')
                ->label('Imagem: tamanho grande')
                ->icon(Heroicon::ArrowsPointingOut)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem(\'large\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { size: \'large\' })'),

            RichEditorTool::make('imagemTamanhoTotal')
                ->label('Imagem: tamanho real')
                ->icon(Heroicon::ArrowsPointingIn)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem(\'full\').run()')
                ->activeJsExpression('$getEditor()?.isActive(\'image\', { size: \'full\' })'),
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
