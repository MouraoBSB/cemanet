<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavDirecionadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_nav_mostra_aba_para_destinatario(): void
    {
        $user = User::factory()->create();
        Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create()
            ->destinatarios()->attach($user->id);

        $this->actingAs($user)->get(route('conta.perfil'))
            ->assertSee('Minhas Direcionadas')
            ->assertSee(route('conta.direcionadas'), false);
    }

    public function test_nav_oculta_para_quem_nao_tem_direcionada(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('conta.perfil'))
            ->assertDontSee(route('conta.direcionadas'), false);
    }
}
