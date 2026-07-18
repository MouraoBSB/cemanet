<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Autorizacao;

use App\Models\AutorEspiritual;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorEspiritualPolicyCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
        (new EstruturaCemaSeeder)->run();   // DEPAE/DECOM antes da semente da config
        $this->seed(TiposConteudoSeeder::class);
    }

    private function depto(string $sigla): Departamento
    {
        return Departamento::where('sigla', $sigla)->firstOrFail();
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

    public function test_responsavel_edita_mesmo_autor_sem_departamento(): void
    {
        // DoTipo: o objeto NÃO é consultado — autor sem depto é editável pelo responsável do TIPO.
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertTrue(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_responsavel_decom_tambem_edita(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DECOM')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertTrue(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_depto_disjunto_nega(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DED')->id]);   // DED não responde por autor
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_sem_permissao_nega(): void
    {
        $u = $this->usuario([], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_sem_departamento_nega(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], []);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_recurso_sem_linha_nega_ate_responsavel(): void
    {
        TipoConteudo::where('recurso', 'autor_espiritual')->delete();   // fail-closed
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_criar_invocado_com_a_classe(): void
    {
        $comDepto = $this->usuario(['autor_espiritual.criar'], [$this->depto('DEPAE')->id]);
        $semDepto = $this->usuario(['autor_espiritual.criar'], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', AutorEspiritual::class));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', AutorEspiritual::class));
    }

    public function test_admin_passa_em_todas_as_acoes(): void
    {
        $admin = $this->admin();
        $autor = AutorEspiritual::factory()->create();

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $autor), $acao);
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', AutorEspiritual::class));
    }
}
