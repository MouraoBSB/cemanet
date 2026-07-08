<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatePainelTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_acessa_o_admin(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin')->assertSuccessful();
    }

    public function test_diretor_nao_acessa_o_admin(): void
    {
        Role::findOrCreate('diretor', 'web');
        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');

        $this->actingAs($diretor)->get('/admin')->assertForbidden();
    }

    public function test_frequentador_nao_acessa_o_admin(): void
    {
        Role::findOrCreate('frequentador', 'web');
        $frequentador = User::factory()->create();
        $frequentador->assignRole('frequentador');

        $this->actingAs($frequentador)->get('/admin')->assertForbidden();
    }
}
