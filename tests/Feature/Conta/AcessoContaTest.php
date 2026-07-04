<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcessoContaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_guest_e_redirecionado_para_login(): void
    {
        $this->get('/minha-conta')->assertRedirect('/entrar');
        $this->get('/minha-conta/perfil')->assertRedirect('/entrar');
    }

    public function test_membro_logado_ve_a_saudacao(): void
    {
        $user = User::factory()->create(['name' => 'Maria Clara', 'ativo' => true]);
        $user->assignRole('frequentador');

        $this->actingAs($user)->get('/minha-conta')->assertOk()->assertSee('Maria Clara');
        $this->actingAs($user)->get('/minha-conta/perfil')->assertOk();
    }

    public function test_pos_login_vai_para_minha_conta(): void
    {
        $user = User::factory()->create(['password' => Hash::make('segredo123'), 'ativo' => true]);

        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/minha-conta');
    }
}
