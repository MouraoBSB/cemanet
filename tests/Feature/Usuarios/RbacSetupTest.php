<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_login_faz_rehash_de_hash_legado(): void
    {
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $user = User::factory()->create(['password' => '$wp'.password_hash($pre, PASSWORD_BCRYPT)]);

        $this->assertTrue(Auth::attempt(['email' => $user->email, 'password' => 'segredo123']));

        $novo = $user->fresh()->password;
        $this->assertStringStartsWith('$2y$', $novo); // virou bcrypt nativo
        $this->assertFalse(str_starts_with($novo, '$wp'));
    }
}
