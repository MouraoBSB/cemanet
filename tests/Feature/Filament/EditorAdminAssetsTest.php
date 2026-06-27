<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace Tests\Feature\Filament;

use Filament\Support\Facades\FilamentAsset;
use Tests\TestCase;

class EditorAdminAssetsTest extends TestCase
{
    public function test_css_do_editor_esta_registrado_no_filament(): void
    {
        // O AdminPanelProvider::boot() roda no bootstrap da app de teste.
        $href = FilamentAsset::getStyleHref('cema-editor', 'app');

        $this->assertNotEmpty($href);
        $this->assertStringContainsString('editor', $href);
    }

    public function test_arquivo_fonte_do_css_do_editor_existe_com_caret_color(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        $this->assertStringContainsString('caret-color', $css);
        $this->assertStringContainsString('editor-conteudo-blog', $css);
    }

    public function test_css_do_editor_espelha_alinhamento_e_tamanho_de_imagem(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        // Escopadas ao canvas do blog, espelhando .conteudo-artigo do front.
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap .alignleft', $css);
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap .size-medium', $css);
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap img.is-resized', $css);
    }

    public function test_css_do_editor_fixa_a_toolbar(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        $this->assertStringContainsString('.fi-fo-rich-editor-toolbar', $css);
        $this->assertStringContainsString('position: sticky', $css);
    }

    public function test_css_do_editor_limita_imagem_sem_classe(): void
    {
        // Regra-base: imagem recém-inserida (ainda sem size-*) não pode aparecer gigante.
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        $this->assertStringContainsString('.editor-conteudo-blog .tiptap img', $css);
        $this->assertStringContainsString('height: auto', $css);
    }
}
