<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Conta\AbaAgenda;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $this->seed(TiposConteudoSeeder::class);   // config de acesso por tipo (agenda => DED+DECOM)
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

    public function test_scope_fail_closed_para_usuario_sem_departamento(): void
    {
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        $semDepto = User::factory()->create();
        $semDepto->assignRole('diretor');

        $this->assertSame(0, AgendaDia::noEscopoDe($semDepto)->count());
    }

    /** Era ..._com_capacidade_e_registro_no_escopo: "registro no escopo" deixou de ser fator (§6.3). */
    public function test_aba_visivel_para_o_responsavel_com_capacidade(): void
    {
        $user = $this->editorDe('DECOM');
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->assertTrue(AbaAgenda::visivelPara($user));
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

    /** §6.3: a aba não consulta registro — responsável vê a aba mesmo com a Agenda VAZIA. */
    public function test_responsavel_ve_a_aba_com_a_agenda_vazia(): void
    {
        $this->assertSame(0, AgendaDia::count(), 'este caso exige a Agenda vazia');

        $this->assertTrue(AbaAgenda::visivelPara($this->editorDe('DED')));
    }

    /** Não-responsável não vê a aba, mesmo com registros existindo. */
    public function test_nao_responsavel_nao_ve_a_aba_mesmo_com_registros(): void
    {
        AgendaDia::factory()->count(3)->create();

        $this->assertFalse(AbaAgenda::visivelPara($this->editorDe('DEPRO')));
    }

    /** Tudo-ou-nada: o responsável enxerga TODOS os registros, inclusive os de pivô disjunto. */
    public function test_scope_do_tipo_e_tudo_ou_nada(): void
    {
        $depro = Departamento::where('sigla', 'DEPRO')->value('id');
        AgendaDia::factory()->count(2)->create()->each(fn ($a) => $a->departamentos()->sync([$depro]));
        AgendaDia::factory()->create();   // pivô vazio

        $this->assertSame(3, AgendaDia::noEscopoDe($this->editorDe('DED'))->count(), 'responsável vê tudo');
        $this->assertSame(0, AgendaDia::noEscopoDe($this->editorDe('DEPRO'))->count(), 'não-responsável vê nada');
    }

    /** §10.3 ("Aba"), 2º portão: o mount do componente aborta 403 para o não-responsável. */
    public function test_nao_responsavel_nao_monta_o_componente(): void
    {
        AgendaDia::factory()->count(3)->create();

        Livewire::actingAs($this->editorDe('DEPRO'))
            ->test(AgendaConta::class)
            ->assertForbidden();
    }
}
