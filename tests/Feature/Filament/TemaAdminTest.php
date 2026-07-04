<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

class TemaAdminTest extends TestCase
{
    private function painel()
    {
        return Filament::getPanel('admin');
    }

    public function test_dark_mode_desligado(): void
    {
        $this->assertFalse($this->painel()->hasDarkMode());
    }

    public function test_marca_usa_os_logos_cema(): void
    {
        $painel = $this->painel();

        $this->assertStringContainsString('logo-horizontal', (string) $painel->getBrandLogo());
        $this->assertSame('2rem', $painel->getBrandLogoHeight());
        $this->assertStringContainsString('logo-icone', (string) $painel->getFavicon());
    }

    public function test_cores_semanticas_cema_registradas(): void
    {
        $chaves = array_keys($this->painel()->getColors());

        foreach (['primary', 'info', 'warning', 'danger', 'success', 'gray'] as $papel) {
            $this->assertContains($papel, $chaves);
        }
    }
}
