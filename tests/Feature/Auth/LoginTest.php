<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_senha_bcrypt(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/minha-conta');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_com_senha_legada_wp_faz_rehash(): void
    {
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $user = User::factory()->create(['password' => '$wp'.password_hash($pre, PASSWORD_BCRYPT), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])->assertRedirect('/minha-conta');
        $this->assertAuthenticatedAs($user);
        $this->assertStringStartsWith('$2y$', $user->fresh()->password); // modernizou
    }

    public function test_conta_inativa_bloqueada_com_mensagem_especifica(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => false]);

        $this->from('/entrar')->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(['email' => 'Sua conta está inativa, entre em contato com o administrador do sistema.']);
        $this->assertGuest();
    }

    public function test_credencial_errada_mensagem_generica_sem_enumeracao(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        // senha errada e e-mail inexistente devem produzir a MESMA mensagem (sem enumeração de usuário).
        // Nota: com config/session.php 'serialization' => 'json', o Store mutila 'errors' para array
        // puro dentro de save() (ver Store::prepareErrorBagForSerialization); só assertSessionHasErrors()
        // força um restart que reconstrói o ViewErrorBag (Store::marshalErrorBag) — por isso usamos a
        // asserção idiomática em vez de ler session('errors') cru.
        $this->from('/entrar')->post('/entrar', ['email' => $user->email, 'password' => 'errada'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors('email');
        $mensagemSenhaErrada = session('errors')->first('email');

        $this->from('/entrar')->post('/entrar', ['email' => 'ninguem@x.com', 'password' => 'errada'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors('email');
        $mensagemEmailInexistente = session('errors')->first('email');

        $this->assertNotEmpty($mensagemSenhaErrada);
        $this->assertSame($mensagemSenhaErrada, $mensagemEmailInexistente);
        $this->assertGuest();
    }

    public function test_lembrar_de_mim_persiste_sessao(): void
    {
        // remember_token explícito em null: o UserFactory popula um valor por padrão,
        // então asserir "não-nulo" sem isso passaria mesmo se o login não setasse nada.
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true, 'remember_token' => null]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123', 'remember' => 'on']);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_rate_limit_dispara_lockout_apos_muitas_tentativas(): void
    {
        Event::fake([Lockout::class]);
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        foreach (range(1, 6) as $tentativa) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada']);
        }

        Event::assertDispatched(Lockout::class); // throttle do pipeline do Fortify (EnsureLoginIsNotThrottled, 5/min) bloqueou
    }
}
