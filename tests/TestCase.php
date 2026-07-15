<?php

namespace Tests;

use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /** Loga um usuário com papel administrador (para os testes do painel Filament). */
    protected function actingAsAdmin(): User
    {
        Role::findOrCreate('administrador', 'web');
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $this->actingAs($user);

        return $user;
    }

    /**
     * Reseta a porta estática de AuditoriaAutorizacao entre testes: o boot() do AgendaConta
     * marca 'perfil' e o estático não some sozinho — sem este reset, vaza para o próximo teste
     * (bomba por ordem de execução). 1 lugar blinda a suíte inteira.
     */
    protected function tearDown(): void
    {
        AuditoriaAutorizacao::usarPorta(null);

        parent::tearDown();
    }
}
