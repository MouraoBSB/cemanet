<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use App\Models\User;
use App\Policies\AgendaDiaPolicy;
use App\Policies\PalestrantePolicy;
use App\Policies\PalestraPolicy;
use App\Policies\PostPolicy;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapacidadeConteudosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class); // as 20 permissions (inclui palestrante.*)
    }

    /** @return array<string, array{class-string<Model>, string}> */
    public static function recursos(): array
    {
        return [
            'palestra' => [Palestra::class, 'palestra'],
            'post' => [Post::class, 'post'],
            'agenda' => [AgendaDia::class, 'agenda'],
            'palestrante' => [Palestrante::class, 'palestrante'],
        ];
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

    private function objeto(string $model, array $departamentoIds = []): Model
    {
        $obj = $model::factory()->create();
        $obj->departamentos()->sync($departamentoIds);

        return $obj;
    }

    public function test_policies_resolvidas_por_auto_discovery(): void
    {
        $this->assertInstanceOf(PalestraPolicy::class, Gate::getPolicyFor(Palestra::class));
        $this->assertInstanceOf(PostPolicy::class, Gate::getPolicyFor(Post::class));
        $this->assertInstanceOf(AgendaDiaPolicy::class, Gate::getPolicyFor(AgendaDia::class));
        $this->assertInstanceOf(PalestrantePolicy::class, Gate::getPolicyFor(Palestrante::class));
    }

    #[DataProvider('recursos')]
    public function test_permite_ver_editar_excluir_com_intersecao(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]);
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_caso_disjunto(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $depro = $this->depto('DEPRO');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]); // usuário no DED
        $obj = $this->objeto($model, [$depro->id]);               // objeto no DEPRO

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_sem_vinculo(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir", "{$recurso}.criar"], []);
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar"); // sem vínculo ⇒ não cria
    }

    #[DataProvider('recursos')]
    public function test_objeto_sem_departamento_so_admin(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]);
        $obj = $this->objeto($model, []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
            $this->assertTrue(Gate::forUser($this->admin())->check($acao, $obj), "admin {$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_sem_a_permissao(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario([], [$ded->id]); // vínculo, mas nenhuma permissão
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar");
    }

    #[DataProvider('recursos')]
    public function test_criar_com_e_sem_departamento(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $comDepto = $this->usuario(["{$recurso}.criar"], [$ded->id]);
        $semDepto = $this->usuario(["{$recurso}.criar"], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', $model));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', $model));
    }

    #[DataProvider('recursos')]
    public function test_nome_cru_nega_mas_ability_permite(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.editar"], [$ded->id]);
        $obj = $this->objeto($model, [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->allows("{$recurso}.editar", $obj)); // nome cru NEGA
        $this->assertTrue(Gate::forUser($u)->check('editar', $obj));              // ability PERMITE
    }

    #[DataProvider('recursos')]
    public function test_visitante_anonimo_negado(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $obj = $this->objeto($model, [$ded->id]);

        $this->assertFalse(Gate::forUser(null)->check('editar', $obj));
    }

    #[DataProvider('recursos')]
    public function test_admin_passa_em_tudo(string $model, string $recurso): void
    {
        $admin = $this->admin();
        $obj = $this->objeto($model, []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', $model));
    }
}
