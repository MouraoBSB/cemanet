<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerfilViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    public function test_frequentador_sem_setor_ve_linha_discreta_e_papel(): void
    {
        $user = User::factory()->create(['name' => 'Ana Sem Setor', 'ativo' => true]);
        $user->assignRole('frequentador');
        $user->perfil()->create(['endereco' => 'Rua X, 100', 'whatsapp' => '61999998888']);

        $this->actingAs($user)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee('Você ainda não atua em um setor da casa')
            ->assertSee('frequentador')
            ->assertSee('Rua X, 100')
            ->assertSee('apenas administrativo'); // selo do endereço
    }

    public function test_membro_com_setor_ve_o_chip_do_setor(): void
    {
        $user = User::factory()->create(['ativo' => true]);
        $user->assignRole('frequentador');
        $user->perfil()->create([]);
        $setor = Setor::create(['nome' => 'Atendimento Fraterno', 'slug' => 'atendimento-fraterno', 'ativo' => true]);
        $user->setores()->attach($setor->id, ['funcao' => 'coordenador']);

        $this->actingAs($user)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee('Atendimento Fraterno')
            ->assertSee('Coordenador');
    }
}
