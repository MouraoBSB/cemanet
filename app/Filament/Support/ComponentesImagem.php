<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Filament\Support;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * Fábrica de componentes de upload de imagem com o tratamento PADRÃO do sistema,
 * reutilizável por qualquer Resource (palestrantes, eventos, etc.): grava na Media
 * Library no disco public, com resize client-side ≤2000px e geração da miniatura.
 * Espelha as regras já usadas no blog.
 */
class ComponentesImagem
{
    /**
     * @param  string  $nome  nome do campo
     * @param  string  $colecao  coleção de mídia do model (ex.: Palestrante::COLECAO_FOTO)
     * @param  bool  $multiplas  true = galeria (múltiplas, reordenáveis, grade)
     */
    public static function upload(string $nome, string $colecao, bool $multiplas = false): SpatieMediaLibraryFileUpload
    {
        $upload = SpatieMediaLibraryFileUpload::make($nome)
            ->collection($colecao)
            ->disk('public')
            ->image()
            ->imageEditor()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2000')
            ->imageResizeUpscale(false)
            ->conversion('thumb');

        if ($multiplas) {
            $upload->multiple()
                ->reorderable()
                ->appendFiles()
                ->panelLayout('grid');
        }

        return $upload;
    }
}
