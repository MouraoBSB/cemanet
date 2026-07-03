<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_e_semeado_com_senha_hasheada_e_loga(): void
    {
        $this->seed(DatabaseSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@cema.local');
        $admin = User::where('email', $email)->first();

        $this->assertNotNull($admin);
        $this->assertStringStartsWith('$2y$', $admin->password); // hasheada, nunca texto plano
        $this->assertTrue(Auth::attempt(['email' => $email, 'password' => env('ADMIN_PASSWORD', 'senha-teste-forte-2026')]));
        $this->assertTrue($admin->hasRole('administrador'));
    }

    public function test_aborta_sem_admin_password(): void
    {
        $orig = getenv('ADMIN_PASSWORD');
        putenv('ADMIN_PASSWORD');
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);

        try {
            $this->expectException(\RuntimeException::class);
            (new AdminSeeder)->run();
        } finally {
            if ($orig !== false) {
                putenv("ADMIN_PASSWORD={$orig}");
                $_ENV['ADMIN_PASSWORD'] = $orig;
                $_SERVER['ADMIN_PASSWORD'] = $orig;
            }
        }
    }
}
