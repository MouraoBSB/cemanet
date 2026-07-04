<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetSenhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicita_link_de_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'quem@x.com']);

        $this->post('/esqueci-a-senha', ['email' => 'quem@x.com'])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_usuario_google_define_senha_via_reset_e_loga(): void
    {
        // usuário criado via Google tem senha aleatória inutilizável, mas tem e-mail
        $user = User::factory()->create([
            'email' => 'google@x.com',
            'google_id' => 'g-abc',
            'password' => Hash::make(Str::random(64)),
        ]);

        $token = Password::broker()->createToken($user);

        $this->post('/redefinir-senha', [
            'token' => $token,
            'email' => 'google@x.com',
            'password' => 'nova-senha-forte-2026',
            'password_confirmation' => 'nova-senha-forte-2026',
        ])->assertSessionHasNoErrors();

        // agora loga com a senha local recém-criada
        $this->post('/entrar', ['email' => 'google@x.com', 'password' => 'nova-senha-forte-2026'])
            ->assertRedirect('/minha-conta');
        $this->assertAuthenticatedAs($user->fresh());
    }
}
