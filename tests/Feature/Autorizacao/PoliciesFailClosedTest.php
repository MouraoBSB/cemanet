<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Palestra;
use App\Models\Post;
use App\Models\User;
use App\Policies\AgendaDiaPolicy;
use App\Policies\PalestraPolicy;
use App\Policies\PostPolicy;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PoliciesFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
    }

    /** @return array<int, array{class-string, string}> */
    public static function recursos(): array
    {
        return [
            [Post::class, 'post'],
            [Palestra::class, 'palestra'],
            [AgendaDia::class, 'agenda'],
        ];
    }

    public function test_policies_sao_resolvidas_por_auto_discovery(): void
    {
        // Falha-primeiro genuíno: sem as classes de policy, getPolicyFor devolve null.
        $this->assertInstanceOf(PostPolicy::class, Gate::getPolicyFor(Post::class));
        $this->assertInstanceOf(PalestraPolicy::class, Gate::getPolicyFor(Palestra::class));
        $this->assertInstanceOf(AgendaDiaPolicy::class, Gate::getPolicyFor(AgendaDia::class));
    }

    #[DataProvider('recursos')]
    public function test_nao_admin_negado_mesmo_com_a_permissao(string $model, string $recurso): void
    {
        $u = User::factory()->create();
        foreach (['ver', 'criar', 'editar', 'excluir'] as $acao) {
            $u->givePermissionTo("{$recurso}.{$acao}");
        }
        $obj = $model::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('ver', $obj), "{$recurso}.ver");
        $this->assertFalse(Gate::forUser($u)->check('editar', $obj), "{$recurso}.editar");
        $this->assertFalse(Gate::forUser($u)->check('excluir', $obj), "{$recurso}.excluir");
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar");
    }

    #[DataProvider('recursos')]
    public function test_admin_passa(string $model, string $recurso): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $obj = $model::factory()->create();

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', $model), "{$recurso}.criar");
    }
}
