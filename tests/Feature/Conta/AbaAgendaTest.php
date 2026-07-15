<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Agenda\AgendaMantenedores;
use App\Support\Conta\AbaAgenda;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AbaAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        Permission::findOrCreate('agenda.ver', 'web');
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver']);
    }

    private function editorDe(string $sigla): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', $sigla)->value('id')]);

        return $user;
    }

    public function test_mantenedores_sao_ded_e_decom(): void
    {
        $esperado = Departamento::whereIn('sigla', ['DED', 'DECOM'])->pluck('id')->sort()->values()->all();

        $this->assertSame($esperado, collect(AgendaMantenedores::ids())->sort()->values()->all());
    }

    public function test_scope_no_escopo_filtra_por_departamento(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');

        $noEscopo = AgendaDia::factory()->create();
        $noEscopo->departamentos()->sync([$decom]);
        $foraEscopo = AgendaDia::factory()->create();
        $foraEscopo->departamentos()->sync([$ded]);

        $user = $this->editorDe('DECOM');
        $ids = AgendaDia::noEscopoDe($user)->pluck('id');

        $this->assertTrue($ids->contains($noEscopo->id));
        $this->assertFalse($ids->contains($foraEscopo->id));
    }

    public function test_scope_fail_closed_para_usuario_sem_departamento(): void
    {
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        $semDepto = User::factory()->create();
        $semDepto->assignRole('diretor');

        $this->assertSame(0, AgendaDia::noEscopoDe($semDepto)->count());
    }

    public function test_aba_visivel_com_capacidade_e_registro_no_escopo(): void
    {
        $user = $this->editorDe('DECOM');
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->assertTrue(AbaAgenda::visivelPara($user));
    }

    public function test_aba_oculta_sem_registro_no_escopo(): void
    {
        $user = $this->editorDe('DECOM'); // tem agenda.ver, mas nenhum AgendaDia no DECOM

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }

    public function test_aba_oculta_sem_capacidade(): void
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador'); // sem agenda.ver
        $user->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }

    public function test_nao_quebra_quando_a_capacidade_nao_esta_no_catalogo(): void
    {
        // Simula ambiente/teste SEM CapacidadesSeeder: a permission não existe no catálogo.
        // A nav renderiza em toda página de conta — visivelPara deve devolver false, não estourar.
        Permission::where('name', 'agenda.ver')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole('frequentador');

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }
}
