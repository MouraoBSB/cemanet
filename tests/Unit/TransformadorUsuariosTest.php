<?php

namespace Tests\Unit;

use App\Importacao\TransformadorUsuarios;
use PHPUnit\Framework\TestCase;

class TransformadorUsuariosTest extends TestCase
{
    private TransformadorUsuarios $t;

    protected function setUp(): void
    {
        $this->t = new TransformadorUsuarios;
    }

    public function test_nome_title_case_com_preposicoes(): void
    {
        $this->assertSame('Ana Karla da Silva', $this->t->nomeTitulo('ANA KARLA DA SILVA'));
        $this->assertSame('Ana Maria de Barros Amaral', $this->t->nomeTitulo('ana maria DE barros amaral'));
        $this->assertSame('Maria da Conceição Rocha', $this->t->nomeTitulo('MARIA DA CONCEIÇÃO ROCHA'));
    }

    public function test_flag_tres_estados(): void
    {
        $this->assertTrue($this->t->flagTresEstados('true'));
        $this->assertTrue($this->t->flagTresEstados('on'));
        $this->assertFalse($this->t->flagTresEstados('FALSE'));
        $this->assertNull($this->t->flagTresEstados(''));
        $this->assertNull($this->t->flagTresEstados(null));
    }

    public function test_papel_precedencia_maior_nivel(): void
    {
        $this->assertSame('diretor', $this->t->papelDe(['trabalhador', 'diretor']));
        $this->assertSame('frequentador', $this->t->papelDe(['frequentador']));
        $this->assertNull($this->t->papelDe(['administrator'])); // ignorado
        $this->assertNull($this->t->papelDe(['subscriber']));
    }

    public function test_resolver_setores_aplica_regra_coordenacao(): void
    {
        $r = $this->t->resolverSetores([
            'coordenador_da_campanha_auta_de_souza',
            'medium',
        ]);

        $slugs = array_column($r, 'funcao', 'slug');
        $this->assertSame('coordenador', $slugs['campanha-auta-de-souza']);
        $this->assertSame('membro', $slugs['medium']);
    }

    public function test_resolver_cargos(): void
    {
        $this->assertSame(['diretor-do-depae', 'tesoureiro'], $this->t->resolverCargos(['diretor_depae', 'tesoureiro']));
    }

    public function test_resolver_setores_coordenador_prevalece_no_mesmo_setor(): void
    {
        // coordenador depois de membro
        $r1 = $this->t->resolverSetores(['caravaneiro_de_auta_de_souza', 'coordenador_da_campanha_auta_de_souza']);
        $this->assertCount(1, $r1);
        $this->assertSame('campanha-auta-de-souza', $r1[0]['slug']);
        $this->assertSame('coordenador', $r1[0]['funcao']);

        // coordenador antes de membro (ordem inversa) — mesmo resultado
        $r2 = $this->t->resolverSetores(['coordenador_da_campanha_auta_de_souza', 'caravaneiro_de_auta_de_souza']);
        $this->assertCount(1, $r2);
        $this->assertSame('coordenador', $r2[0]['funcao']);
    }

    public function test_nome_titulo_primeira_palavra_preposicao_fica_maiuscula(): void
    {
        $this->assertSame('De Souza Santos', $this->t->nomeTitulo('de souza santos'));
    }
}
