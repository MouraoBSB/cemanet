<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcessoAgendaContaTest extends TestCase
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

    private function editorDecomComAgenda(): User
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create()->departamentos()->sync([$decom]);

        return $user;
    }

    public function test_editor_no_escopo_acessa_a_rota(): void
    {
        $this->actingAs($this->editorDecomComAgenda())
            ->get(route('conta.agenda'))
            ->assertOk();
    }

    public function test_usuario_sem_capacidade_recebe_403(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('frequentador');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create()->departamentos()->sync([$decom]);

        $this->actingAs($user)->get(route('conta.agenda'))->assertForbidden();
    }

    /**
     * Era test_editor_sem_registro_no_escopo_recebe_403: a premissa ("a aba exige registro no
     * escopo") é a decisão 1 da Fase D, REVOGADA pelo §6.3. Mesmo arranjo, asserção invertida.
     */
    public function test_responsavel_sem_registro_acessa_a_rota(): void
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        // nenhum AgendaDia criado — e a aba abre assim mesmo: o DECOM responde pela Agenda

        $this->actingAs($user)->get(route('conta.agenda'))->assertOk();
    }

    /** O outro eixo do 403 (§10.3, 1º portão): não-responsável, COM registros existindo. */
    public function test_nao_responsavel_recebe_403_mesmo_com_registros(): void
    {
        $depro = Departamento::where('sigla', 'DEPRO')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$depro]);
        // Pivô EXPLÍCITO e coincidente com o usuário: os registros existem e o pivô intersecta o
        // DEPRO — e ainda assim nega, porque DEPRO não responde pela Agenda (DED+DECOM). Este
        // arranjo reprova também a variante permissiva (||) na porta da rota.
        AgendaDia::factory()->count(3)->create()->each(fn ($a) => $a->departamentos()->sync([$depro]));

        $this->actingAs($user)->get(route('conta.agenda'))->assertForbidden();
    }

    public function test_visitante_anonimo_e_redirecionado_ao_login(): void
    {
        $this->get(route('conta.agenda'))->assertRedirect(route('login'));
    }

    public function test_nav_mostra_aba_para_editor_no_escopo(): void
    {
        $this->actingAs($this->editorDecomComAgenda())
            ->get(route('conta.perfil'))
            ->assertSee('Agenda');
    }

    public function test_nav_oculta_aba_para_quem_nao_tem_acesso(): void
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador');

        $this->actingAs($user)->get(route('conta.perfil'))->assertDontSee(route('conta.agenda'));
    }
}
