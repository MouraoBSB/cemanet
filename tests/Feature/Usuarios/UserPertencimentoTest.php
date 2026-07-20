<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPertencimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_eh_medium_pelo_setor(): void
    {
        $medium = User::factory()->create();
        $medium->setores()->attach(Setor::where('slug', Setor::SLUG_MEDIUM)->value('id'), ['funcao' => 'membro']);
        $outro = User::factory()->create();

        $this->assertTrue($medium->fresh()->ehMedium());
        $this->assertFalse($outro->ehMedium());
    }

    public function test_eh_diretor_depae_e_presidente_pelo_cargo(): void
    {
        $depae = User::factory()->create();
        $depae->cargos()->attach(Cargo::where('slug', Cargo::SLUG_DIRETOR_DEPAE)->value('id'));

        $presidente = User::factory()->create();
        $presidente->cargos()->attach(Cargo::where('slug', Cargo::SLUG_PRESIDENTE)->value('id'));

        $this->assertTrue($depae->fresh()->ehDiretorDepae());
        $this->assertFalse($depae->ehPresidente());
        $this->assertTrue($presidente->fresh()->ehPresidente());
        $this->assertFalse($presidente->ehDiretorDepae());
    }
}
