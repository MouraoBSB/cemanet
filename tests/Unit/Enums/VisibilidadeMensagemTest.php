<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeMensagem;
use PHPUnit\Framework\TestCase;

class VisibilidadeMensagemTest extends TestCase
{
    public function test_tem_os_seis_niveis_com_slugs_reais(): void
    {
        $values = array_map(fn (VisibilidadeMensagem $v) => $v->value, VisibilidadeMensagem::cases());
        $this->assertSame(
            ['publico', 'trabalhadores', 'mediuns-trabalhadores', 'diretores', 'diretor-depae', 'direcionada'],
            $values,
        );
    }

    public function test_nivel_minimo_escada_vs_recorte(): void
    {
        $this->assertSame(0, VisibilidadeMensagem::Publico->nivelMinimo());
        $this->assertSame(20, VisibilidadeMensagem::Trabalhadores->nivelMinimo());
        $this->assertSame(30, VisibilidadeMensagem::Diretores->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::Mediuns->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::DiretorDepae->nivelMinimo());
        $this->assertNull(VisibilidadeMensagem::Direcionada->nivelMinimo());
    }

    public function test_eh_recorte(): void
    {
        $this->assertFalse(VisibilidadeMensagem::Publico->ehRecorte());
        $this->assertFalse(VisibilidadeMensagem::Trabalhadores->ehRecorte());
        $this->assertFalse(VisibilidadeMensagem::Diretores->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::DiretorDepae->ehRecorte());
        $this->assertTrue(VisibilidadeMensagem::Direcionada->ehRecorte());
    }

    public function test_opcoes_mapeia_value_para_rotulo(): void
    {
        $opcoes = VisibilidadeMensagem::opcoes();
        $this->assertSame('Público', $opcoes['publico']);
        $this->assertSame('Médiuns', $opcoes['mediuns-trabalhadores']);
        $this->assertSame('Diretor do DEPAE', $opcoes['diretor-depae']);
        $this->assertCount(6, $opcoes);
    }
}
