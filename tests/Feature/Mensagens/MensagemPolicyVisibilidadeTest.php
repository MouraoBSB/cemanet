<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MensagemPolicyVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    private function usuario(string $papel): User
    {
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u->fresh();
    }

    public function test_view_delega_a_pode_ser_visto_por(): void
    {
        $publica = Mensagem::factory()->create(['nivel' => 'publico', 'slug' => 'p-pub']);
        $restrita = Mensagem::factory()->create(['nivel' => 'diretores', 'slug' => 'p-dir']);

        $this->assertTrue(Gate::forUser($this->usuario('diretor'))->allows('view', $restrita));
        $this->assertFalse(Gate::forUser($this->usuario('frequentador'))->allows('view', $restrita));
        $this->assertFalse(Gate::forUser(null)->allows('view', $restrita)); // anônimo negado no restrito
        $this->assertTrue(Gate::forUser(null)->allows('view', $publica));    // anônimo vê a pública
        $this->assertTrue(Gate::forUser($this->usuario('administrador'))->allows('view', $restrita)); // Gate::before
    }

    public function test_view_any_sempre_permitido(): void
    {
        $this->assertTrue(Gate::forUser($this->usuario('frequentador'))->allows('viewAny', Mensagem::class));
        $this->assertTrue(Gate::forUser(null)->allows('viewAny', Mensagem::class));
    }
}
