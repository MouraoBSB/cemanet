<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace Tests\Feature\Filament;

use App\Filament\RichContent\Plugins\ImagemPlugin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Tests\TestCase;

/**
 * Testa o round-trip do atributo `align` da extensão ImagemPlugin.
 * Usa RichContentRenderer diretamente (headless, sem container Filament)
 * para confirmar que image+align:left → class="alignleft" no HTML renderizado.
 *
 * Nota: o brief original sugere RichEditor::make()->getTipTapEditor(),
 * mas esse caminho exige um Schema/container inicializado (lança
 * "container must not be accessed before initialization"). O contrato
 * real é o mesmo: RichContentRenderer::make()->plugins()->getEditor()
 * é exatamente o que RichEditor::getTipTapEditor() chama internamente.
 */
class RichEditorImagemRoundtripTest extends TestCase
{
    private function editor(): \Tiptap\Editor
    {
        return RichContentRenderer::make()
            ->plugins([ImagemPlugin::make()])
            ->getEditor();
    }

    public function test_atributo_align_sobrevive_ao_getHTML(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/a.jpg', 'align' => 'left'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringContainsString('alignleft', $html);
    }

    public function test_align_right_gera_classe_alignright(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/b.jpg', 'align' => 'right'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringContainsString('alignright', $html);
    }

    public function test_imagem_sem_align_nao_emite_classe_wp(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/c.jpg'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringNotContainsString('alignleft', $html);
        $this->assertStringNotContainsString('alignright', $html);
        $this->assertStringNotContainsString('aligncenter', $html);
        $this->assertStringNotContainsString('alignnone', $html);
    }
}
