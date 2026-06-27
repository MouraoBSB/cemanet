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
}
