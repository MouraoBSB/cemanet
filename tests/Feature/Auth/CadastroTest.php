<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_cadastro_cria_frequentador_com_perfil_e_verificado(): void
    {
        $this->post('/cadastro', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@exemplo.com',
            'password' => 'senha-super-forte-2026',
            'password_confirmation' => 'senha-super-forte-2026',
        ])->assertRedirect('/');

        $user = User::where('email', 'fulano@exemplo.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('frequentador'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->perfil); // perfis_membro 1:1 criado
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_duplicado_rejeitado(): void
    {
        User::factory()->create(['email' => 'ja@existe.com']);

        $this->from('/cadastro')->post('/cadastro', [
            'name' => 'X', 'email' => 'ja@existe.com',
            'password' => 'senha-super-forte-2026', 'password_confirmation' => 'senha-super-forte-2026',
        ])->assertRedirect('/cadastro')->assertSessionHasErrors('email');
    }

    public function test_rate_limit_no_cadastro_apos_6_tentativas(): void
    {
        foreach (range(1, 6) as $i) {
            // e-mail sem "@" falha a validação (nenhum usuário criado), mas a requisição conta no throttle:6,1
            $this->post('/cadastro', ['name' => 'X', 'email' => "tentativa{$i}", 'password' => 'x', 'password_confirmation' => 'x']);
        }

        $this->post('/cadastro', ['name' => 'X', 'email' => 'tentativa7', 'password' => 'x', 'password_confirmation' => 'x'])
            ->assertStatus(429); // 7ª tentativa no mesmo minuto barrada pelo throttle da rota
    }
}
