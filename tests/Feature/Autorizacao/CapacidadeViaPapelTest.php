<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Autorizacao;

use App\Importacao\GlossarioUsuarios;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
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
        $this->seed(CapacidadesSeeder::class); // permissions do glossário
        $this->seed(TiposConteudoSeeder::class); // config de acesso por tipo (palestra ⇒ DED)
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
        // DED nos dois lados: é o responsável por 'palestra' na semente (e intersecta o objeto,
        // então o caso é neutro tanto no filtro por registro quanto no regime "do tipo").
        $diretor = $this->diretorNos(['DED']);
        $palestra = $this->palestraNos(['DED']);

        // sem permission no papel ⇒ negado
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz concede ao papel ⇒ permitido
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');
        $this->assertTrue(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz revoga ⇒ negado
        Role::findByName('diretor', 'web')->syncPermissions([]);
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));
    }

    /**
     * O presidente (8 vínculos) inclui o DED, responsável por palestra ⇒ edita.
     * Antes varria 3 siglas do pivô do objeto; sob o regime "do tipo" isso seria tautologia
     * (o objeto não é consultado — ver test_regime_do_tipo_ignora_o_pivo_do_objeto).
     */
    public function test_presidente_diretor_edita_palestra_por_estar_no_departamento_responsavel(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $presidente = $this->diretorNos(array_keys(GlossarioUsuarios::DEPARTAMENTOS)); // 8 vínculos

        $this->assertTrue(Gate::forUser($presidente)->check('editar', $this->palestraNos(['DED'])));
    }

    /**
     * O caso canônico do congelamento (§6.4 + I9). Substitui o antigo
     * test_decom_edita_palestra_com_dois_departamentos_por_intersecao, cuja premissa (a exceção
     * por objeto da Fase C) a decisão 4 do §5 matou. Guardião do item 5 do §11: se algum caminho
     * voltar a ler o pivô para autorizar, alguma destas 3 fases fica vermelha.
     */
    public function test_regime_do_tipo_ignora_o_pivo_do_objeto(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');
        // A semente dá palestra ⇒ "do tipo", responsável = DED (TiposConteudoSeeder).

        $ded = $this->diretorNos(['DED']);

        // 1) pivô do objeto DED+DECOM ⇒ permite (o objeto não é consultado)
        $this->assertTrue(Gate::forUser($ded)->check('editar', $this->palestraNos(['DED', 'DECOM'])));

        // 2) pivô do objeto SÓ DECOM (disjunto do usuário) ⇒ permite mesmo assim — o congelamento
        $this->assertTrue(Gate::forUser($ded)->check('editar', $this->palestraNos(['DECOM'])));

        // 3) usuário no DECOM (NÃO responsável), pivô coincidente ⇒ nega — o pivô não abre nada
        $decom = $this->diretorNos(['DECOM']);
        $this->assertFalse(Gate::forUser($decom)->check('editar', $this->palestraNos(['DECOM'])));
    }
}
