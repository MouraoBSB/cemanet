<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VincularPresidentesDepartamentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_vincula_presidente_aos_8_departamentos(): void
    {
        $presidente = User::factory()->create();
        $presidente->cargos()->sync([Cargo::where('slug', 'presidente')->value('id')]);

        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();

        $this->assertSame(Departamento::count(), $presidente->departamentos()->count());
        $this->assertSame(8, $presidente->departamentos()->count());
    }

    public function test_idempotente_e_ignora_nao_presidentes(): void
    {
        $presidente = User::factory()->create();
        $presidente->cargos()->sync([Cargo::where('slug', 'presidente')->value('id')]);
        $outro = User::factory()->create();
        $outro->cargos()->sync([Cargo::where('slug', 'diretor-do-ded')->value('id')]);

        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();
        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();

        $this->assertSame(8, $presidente->refresh()->departamentos()->count());
        $this->assertSame(0, $outro->refresh()->departamentos()->count()); // não é presidente
    }
}
