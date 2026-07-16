<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Unit\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\AgendaDia;
use App\Models\Palestrante;
use App\Support\Autorizacao\GlossarioCapacidades;
use PHPUnit\Framework\TestCase;

class GlossarioCapacidadesMapaTest extends TestCase
{
    public function test_mapa_cobre_exatamente_os_recursos_do_glossario(): void
    {
        // Canonicalizing: cobertura sem amarrar a ORDEM — reordenar RECURSOS não é defeito.
        $this->assertEqualsCanonicalizing(
            GlossarioCapacidades::RECURSOS,
            array_keys(GlossarioCapacidades::RECURSOS_MODELS),
        );
    }

    public function test_mapa_resolve_os_dois_casos_em_que_slug_difere_do_model(): void
    {
        $this->assertSame(AgendaDia::class, GlossarioCapacidades::modelDe('agenda'));
        $this->assertSame(Palestrante::class, GlossarioCapacidades::modelDe('palestrante'));
    }

    public function test_model_de_recurso_inexistente_devolve_null(): void
    {
        $this->assertNull(GlossarioCapacidades::modelDe('inexistente'));
    }

    public function test_todo_model_do_mapa_existe(): void
    {
        foreach (GlossarioCapacidades::RECURSOS_MODELS as $recurso => $model) {
            $this->assertTrue(class_exists($model), "Model do recurso '{$recurso}' não existe: {$model}");
        }
    }

    public function test_regime_tem_os_dois_casos_e_rotulos_em_pt_br(): void
    {
        $this->assertSame('do_tipo', RegimeAcesso::DoTipo->value);
        $this->assertSame('por_registro', RegimeAcesso::PorRegistro->value);
        $this->assertSame('Departamentos fixos do tipo', RegimeAcesso::DoTipo->rotulo());
        $this->assertSame('Departamentos definidos em cada registro', RegimeAcesso::PorRegistro->rotulo());
    }

    public function test_opcoes_devolve_mapa_value_rotulo_para_o_select(): void
    {
        $this->assertSame([
            'do_tipo' => 'Departamentos fixos do tipo',
            'por_registro' => 'Departamentos definidos em cada registro',
        ], RegimeAcesso::opcoes());
    }
}
