<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Enums\VisibilidadeEvento;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoPolicyCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
    }

    private function usuario(array $permissoes = [], array $departamentoIds = []): User
    {
        $u = User::factory()->create();
        foreach ($permissoes as $p) {
            $u->givePermissionTo($p);
        }
        $u->departamentos()->sync($departamentoIds);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('administrador');

        return $u;
    }

    private function depto(string $sigla): Departamento
    {
        return Departamento::create(['sigla' => $sigla, 'nome' => $sigla, 'slug' => strtolower($sigla)]);
    }

    private function evento(string $slug, array $departamentoIds = []): Evento
    {
        $e = Evento::create([
            'titulo' => 'E', 'slug' => $slug, 'data_inicio' => '2026-08-15',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_RASCUNHO,
        ]);
        $e->departamentos()->sync($departamentoIds);

        return $e;
    }

    public function test_permite_ver_editar_excluir_quando_ha_intersecao(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.ver', 'evento.editar', 'evento.excluir'], [$ded->id]);
        $e = $this->evento('interseccao', [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $e), $acao);
        }
    }

    public function test_nega_no_caso_disjunto(): void
    {
        $ded = $this->depto('DED');
        $depro = $this->depto('DEPRO');
        $u = $this->usuario(['evento.editar'], [$ded->id]);   // usuário no DED
        $e = $this->evento('disjunto', [$depro->id]);          // evento no DEPRO

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_nega_sem_vinculo_de_departamento(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], []);
        $e = $this->evento('sem-vinculo', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_objeto_sem_departamento_so_admin(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], [$ded->id]);
        $e = $this->evento('objeto-sem-depto', []);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
        $this->assertTrue(Gate::forUser($this->admin())->check('editar', $e));
    }

    public function test_nega_sem_a_permissao(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario([], [$ded->id]);                   // vínculo, mas sem a permissão
        $e = $this->evento('sem-permissao', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_criar_invocado_com_a_classe(): void
    {
        $ded = $this->depto('DED');
        $comDepto = $this->usuario(['evento.criar'], [$ded->id]);
        $semDepto = $this->usuario(['evento.criar'], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', Evento::class));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', Evento::class));
    }

    public function test_nome_cru_nega_mas_ability_da_policy_permite(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], [$ded->id]);
        $e = $this->evento('nome-cru', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->allows('evento.editar', $e)); // nome cru NEGA
        $this->assertTrue(Gate::forUser($u)->check('editar', $e));          // ability de policy PERMITE
    }

    public function test_visitante_anonimo_negado(): void
    {
        $ded = $this->depto('DED');
        $e = $this->evento('anonimo', [$ded->id]);

        $this->assertFalse(Gate::forUser(null)->check('editar', $e));
    }

    public function test_admin_passa_em_todas_as_acoes(): void
    {
        $admin = $this->admin();
        $e = $this->evento('admin', []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $e), $acao);
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', Evento::class));
    }
}
