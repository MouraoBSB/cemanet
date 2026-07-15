<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\Cargo;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrigirPapelDiretoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_promove_trabalhador_com_cargo_de_diretor_para_diretor(): void
    {
        $cargoDed = Cargo::where('slug', 'diretor-do-ded')->firstOrFail(); // departamento_id NOT NULL, institucional false
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->cargos()->sync([$cargoDed->id]);

        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('diretor'));
        $this->assertFalse($user->hasRole('trabalhador'));
    }

    public function test_idempotente_e_nao_promove_quem_nao_deve(): void
    {
        $cargoDed = Cargo::where('slug', 'diretor-do-ded')->firstOrFail();
        $alvo = User::factory()->create();
        $alvo->assignRole('trabalhador');
        $alvo->cargos()->sync([$cargoDed->id]);

        $intocado = User::factory()->create(); // trabalhador sem cargo de diretor
        $intocado->assignRole('trabalhador');

        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful();
        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful(); // 2ª vez: no-op

        $this->assertTrue($alvo->refresh()->hasRole('diretor'));
        $this->assertFalse($alvo->hasRole('trabalhador'));
        $this->assertTrue($intocado->refresh()->hasRole('trabalhador'));
        $this->assertFalse($intocado->hasRole('diretor'));
    }
}
