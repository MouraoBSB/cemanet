<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Autorizacao;

use App\Importacao\GlossarioUsuarios;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapacidadeViaPapelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // papéis + 8 departamentos
        $this->seed(CapacidadesSeeder::class); // 20 permissions
    }

    private function diretorNos(array $siglas): User
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $ids = Departamento::whereIn('sigla', $siglas)->pluck('id')->all();
        $u->departamentos()->sync($ids);

        return $u;
    }

    private function palestraNos(array $siglas): Palestra
    {
        $p = Palestra::factory()->create();
        $ids = Departamento::whereIn('sigla', $siglas)->pluck('id')->all();
        $p->departamentos()->sync($ids);

        return $p;
    }

    public function test_usuario_do_papel_ganha_e_perde_capacidade(): void
    {
        $diretor = $this->diretorNos(['DECOM']);
        $palestra = $this->palestraNos(['DECOM']);

        // sem permission no papel ⇒ negado
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz concede ao papel ⇒ permitido
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');
        $this->assertTrue(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz revoga ⇒ negado
        Role::findByName('diretor', 'web')->syncPermissions([]);
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));
    }

    public function test_presidente_diretor_com_8_deptos_edita_qualquer_departamento(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $presidente = $this->diretorNos(array_keys(GlossarioUsuarios::DEPARTAMENTOS)); // 8 vínculos

        foreach (['DED', 'DECOM', 'DEPRO'] as $sigla) {
            $palestra = $this->palestraNos([$sigla]);
            $this->assertTrue(Gate::forUser($presidente)->check('editar', $palestra), $sigla);
        }
    }

    public function test_decom_edita_palestra_com_dois_departamentos_por_intersecao(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $diretorDecom = $this->diretorNos(['DECOM']);

        // caso DECOM: palestra pertence a DOIS departamentos (DED + DECOM)
        $doisDeptos = $this->palestraNos(['DED', 'DECOM']);
        $this->assertTrue(Gate::forUser($diretorDecom)->check('editar', $doisDeptos));

        // disjunto: palestra só em DED ⇒ diretor do DECOM é negado
        $soDed = $this->palestraNos(['DED']);
        $this->assertFalse(Gate::forUser($diretorDecom)->check('editar', $soDed));
    }
}
