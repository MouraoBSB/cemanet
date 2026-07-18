<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use App\Support\Usuarios\IntegridadePapel;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegridadePapelAsseguraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    private function medium(): Setor
    {
        return Setor::where('slug', 'medium')->firstOrFail();
    }

    private function cargoDiretorDed(): Cargo
    {
        return Cargo::where('nome', 'Diretor do DED')->firstOrFail();
    }

    public function test_assegurar_lanca_para_frequentador_com_setor(): void
    {
        $u = User::factory()->create();
        $u->assignRole('frequentador');
        $u->setores()->attach($this->medium()->id);

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }

    public function test_assegurar_lanca_para_trabalhador_com_cargo(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->cargos()->attach($this->cargoDiretorDed()->id);

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }

    public function test_assegurar_e_silencioso_para_trabalhador_com_setor(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id);

        IntegridadePapel::assegurar($u);
        $this->assertTrue(true); // não lançou
    }

    public function test_assegurar_e_silencioso_para_diretor_com_cargo(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $u->cargos()->attach($this->cargoDiretorDed()->id);

        IntegridadePapel::assegurar($u);
        $this->assertTrue(true);
    }

    public function test_assegurar_e_silencioso_para_sem_papel_sem_estrutura(): void
    {
        // Os 4 sem-papel do dev não têm setor/cargo => não violam R1/R2.
        IntegridadePapel::assegurar(User::factory()->create());
        $this->assertTrue(true);
    }

    public function test_assegurar_le_o_nivel_por_query_fresca_nao_o_cache(): void
    {
        // Papel diretor em memória (cacheado), mas rebaixado no banco para frequentador: assegurar
        // deve enxergar o nível FRESCO (10) e lançar por causa do setor.
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $u->setores()->attach($this->medium()->id);
        $u->load('roles'); // aquece a coleção cacheada com 'diretor'

        $u->syncRoles(['frequentador']); // muda no banco; a coleção carregada segue 'diretor'

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }
}
