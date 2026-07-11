<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartamentoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_nasce_vazia_e_relaciona_usuario_a_departamentos(): void
    {
        $this->assertSame(0, DB::table('departamento_usuario')->count()); // nasce vazia

        $user = User::factory()->create();
        $ded = Departamento::create(['sigla' => 'DED', 'nome' => 'Estudos Doutrinários', 'slug' => 'ded']);
        $depro = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $user->departamentos()->attach([$ded->id, $depro->id]);

        $this->assertSame(2, $user->departamentos()->count());
        $this->assertTrue($user->departamentos()->where('departamentos.id', $ded->id)->exists());

        $user->departamentos()->detach($ded->id);
        $this->assertSame(1, $user->departamentos()->count());
    }
}
