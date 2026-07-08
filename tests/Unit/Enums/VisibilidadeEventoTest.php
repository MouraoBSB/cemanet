<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeEvento;
use PHPUnit\Framework\TestCase;

class VisibilidadeEventoTest extends TestCase
{
    public function test_niveis_minimos_sao_a_hierarquia_de_papeis(): void
    {
        $this->assertSame(0, VisibilidadeEvento::Publico->nivelMinimo());
        $this->assertSame(10, VisibilidadeEvento::Logados->nivelMinimo());
        $this->assertSame(20, VisibilidadeEvento::Trabalhadores->nivelMinimo());
        $this->assertSame(30, VisibilidadeEvento::Diretoria->nivelMinimo());
    }

    public function test_opcoes_mapeia_valor_para_rotulo(): void
    {
        $opcoes = VisibilidadeEvento::opcoes();

        $this->assertSame('Público', $opcoes['publico']);
        $this->assertSame('Somente diretoria', $opcoes['diretoria']);
        $this->assertCount(4, $opcoes);
    }
}
