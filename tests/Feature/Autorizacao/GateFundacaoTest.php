<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateFundacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passa_em_qualquer_ability(): void
    {
        Role::findOrCreate('administrador', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        // Ability arbitrária, sem policy: só o Gate::before do admin pode aprovar.
        $this->assertTrue(Gate::forUser($admin)->allows('ability-arbitraria-sem-policy'));
    }

    public function test_nao_admin_nao_passa_por_ability_sem_policy(): void
    {
        $u = User::factory()->create();

        $this->assertFalse(Gate::forUser($u)->allows('ability-arbitraria-sem-policy'));
    }

    public function test_nome_cru_de_permissao_nao_e_ability_com_flag_off(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $u = User::factory()->create();      // não-admin
        $u->givePermissionTo('evento.editar'); // tem a permissão...

        // ...mas com o flag OFF o nome cru não resolve como ability de gate.
        $this->assertFalse(Gate::forUser($u)->allows('evento.editar'));
        // A posse em si continua verdadeira pelo método direto do trait:
        $this->assertTrue($u->hasPermissionTo('evento.editar'));
    }
}
