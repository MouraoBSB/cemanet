<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatePainelTest extends TestCase
{
    use RefreshDatabase;

    public function test_diretor_acessa_e_frequentador_nao(): void
    {
        Role::findOrCreate('diretor', 'web');
        Role::findOrCreate('frequentador', 'web');
        $painel = Filament::getPanel('admin');

        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $freq = User::factory()->create();
        $freq->assignRole('frequentador');

        $this->assertTrue($diretor->canAccessPanel($painel));
        $this->assertFalse($freq->canAccessPanel($painel));
    }
}
