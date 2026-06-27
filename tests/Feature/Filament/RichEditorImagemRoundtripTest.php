<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace Tests\Feature\Filament;

use App\Filament\RichContent\Plugins\ImagemPlugin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Tests\TestCase;

/**
 * Testa o round-trip dos atributos `align` e `size` da extensão ImagemPlugin.
 * Usa RichContentRenderer diretamente (headless, sem container Filament)
 * para confirmar que:
 *   - image+align → classe alignleft/alignright/etc. no HTML
 *   - image+size → classe size-medium/size-large/size-full no HTML
 *   - image+align+size → AMBAS as classes coexistem no mesmo img
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

    // --- Testes de align ---

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

    // --- Testes de size ---

    public function test_atributo_size_large_gera_classe_size_large(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/d.jpg', 'size' => 'large'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringContainsString('size-large', $html);
    }

    public function test_atributo_size_medium_gera_classe_size_medium(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/e.jpg', 'size' => 'medium'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringContainsString('size-medium', $html);
    }

    public function test_atributo_size_full_gera_classe_size_full(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/f.jpg', 'size' => 'full'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        $this->assertStringContainsString('size-full', $html);
    }

    // --- Teste de coexistência align + size ---

    public function test_align_e_size_coexistem_na_mesma_classe(): void
    {
        $doc = [
            'type'    => 'doc',
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type'  => 'image',
                    'attrs' => ['src' => 'https://x/g.jpg', 'align' => 'left', 'size' => 'large'],
                ]],
            ]],
        ];

        $html = $this->editor()->setContent($doc)->getHTML();

        // Ambas as classes devem aparecer no mesmo elemento img.
        // tiptap-php usa HTML::mergeAttributes que concatena class strings —
        // não sobrescreve. Confirmado em Utils/HTML.php linha 21.
        $this->assertStringContainsString('alignleft', $html);
        $this->assertStringContainsString('size-large', $html);
    }
}
