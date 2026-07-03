<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_recebe_papel_com_nivel(): void
    {
        $papel = Role::create(['name' => 'diretor', 'nivel' => 30]);
        $user = User::factory()->create();

        $user->assignRole($papel);

        $this->assertTrue($user->hasRole('diretor'));
        $this->assertSame(30, (int) Role::findByName('diretor')->nivel);
    }
}
