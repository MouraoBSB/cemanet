<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VincularDiretoresDepartamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // departamentos + cargos (diretor_* e institucionais)
    }

    private function cargo(string $slug): Cargo
    {
        return Cargo::where('slug', $slug)->firstOrFail();
    }

    public function test_diretor_de_departamento_recebe_o_vinculo_e_e_idempotente(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();
        $user = User::factory()->create();
        $user->cargos()->attach($this->cargo('diretor-do-ded')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();
        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful(); // idempotente

        $this->assertSame([$ded->id], $user->fresh()->departamentos()->pluck('departamentos.id')->all());
    }

    public function test_cargo_institucional_sem_departamento_nao_gera_vinculo(): void
    {
        $user = User::factory()->create();
        // diretor_presidente → nome 'Presidente' → slug 'presidente'; departamento_id null.
        $user->cargos()->attach($this->cargo('presidente')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertSame(0, $user->fresh()->departamentos()->count());
    }

    public function test_das_fica_sem_vinculo_por_nao_ter_ocupante(): void
    {
        // O cargo 'diretor-do-das' existe (CARGOS_EXTRA), mas ninguém o ocupa.
        $das = Departamento::where('sigla', 'DAS')->first();
        $user = User::factory()->create();
        $user->cargos()->attach($this->cargo('diretor-do-ded')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertSame(0, DB::table('departamento_usuario')->where('departamento_id', $das->id)->count());
    }

    public function test_invariante_cargo_nao_diretor_com_departamento_tambem_vincula(): void
    {
        // O filtro é "cargo com departamento", NÃO o slug 'diretor_*'.
        $depro = Departamento::where('sigla', 'DEPRO')->first();
        $coordenador = Cargo::create([
            'nome' => 'Coordenador de Eventos', 'slug' => 'coordenador-de-eventos',
            'departamento_id' => $depro->id, 'institucional' => false,
        ]);
        $user = User::factory()->create();
        $user->cargos()->attach($coordenador->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertTrue($user->fresh()->departamentos()->where('departamentos.id', $depro->id)->exists());
    }
}
