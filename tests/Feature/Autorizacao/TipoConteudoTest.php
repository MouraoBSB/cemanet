<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoConteudoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // 8 departamentos
    }

    public function test_regime_e_castado_para_o_enum(): void
    {
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);

        $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', 'agenda')->first()->regime);
    }

    public function test_recurso_e_unique(): void
    {
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);

        $this->expectException(QueryException::class);
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::PorRegistro]);
    }

    public function test_relacao_departamentos_e_a_inversa_conversam(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();

        $tipo->departamentos()->sync([$ded->id]);

        $this->assertSame(['DED'], $tipo->fresh()->departamentos->pluck('sigla')->all());
        $this->assertSame(['agenda'], $ded->fresh()->tiposConteudo->pluck('recurso')->all());
    }

    public function test_pivo_nao_duplica_o_mesmo_departamento(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();

        $tipo->departamentos()->sync([$ded->id, $ded->id]);

        $this->assertSame(1, $tipo->departamentos()->count());
    }

    public function test_excluir_departamento_responsavel_e_barrado_pela_fk(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();
        $tipo->departamentos()->sync([$ded->id]);

        $this->expectException(QueryException::class);
        $ded->delete();
    }
}
