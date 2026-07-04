<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\Palestra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function membro(): User
    {
        $u = User::factory()->create(['ativo' => true]);
        $u->assignRole('frequentador');

        return $u;
    }

    public function test_painel_lista_proxima_palestra_de_hoje_ate_o_fim_do_dia(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra de Hoje',
            'data_da_palestra' => Carbon::today()->addHours(6)->subMinutes(1), // já passou o horário, mas é hoje
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->actingAs($this->membro())->get('/minha-conta')
            ->assertOk()->assertSee('Palestra de Hoje');
    }

    public function test_painel_estado_vazio_sem_proximas(): void
    {
        Palestra::factory()->create([
            'data_da_palestra' => Carbon::yesterday(),
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->actingAs($this->membro())->get('/minha-conta')
            ->assertOk()->assertSee('Nenhuma palestra agendada');
    }
}
