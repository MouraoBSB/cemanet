<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeaderAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_visitante_ve_links_de_entrar_e_cadastrar(): void
    {
        $this->get('/')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertSee('Entrar')
            ->assertSee('Cadastrar')
            ->assertDontSee('Minha Conta')
            ->assertDontSee('Sair');
    }

    public function test_membro_logado_ve_menu_da_conta(): void
    {
        $user = User::factory()->create(['name' => 'Bruno Alves', 'ativo' => true]);
        $user->assignRole('frequentador');

        $this->actingAs($user)->get('/')
            ->assertSee('Minha Conta')
            ->assertSee('Sair')
            ->assertSee('Bruno') // primeiro nome na saudação do header
            ->assertDontSee('Cadastrar');
    }
}
