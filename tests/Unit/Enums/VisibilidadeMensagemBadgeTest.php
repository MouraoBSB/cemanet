<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeMensagem;
use PHPUnit\Framework\TestCase;

class VisibilidadeMensagemBadgeTest extends TestCase
{
    public function test_eh_restrito_e_diferente_de_eh_recorte(): void
    {
        // ehRestrito = != Publico (inclui a escada Trabalhadores/Diretores).
        $this->assertFalse(VisibilidadeMensagem::Publico->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Trabalhadores->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Diretores->ehRestrito());
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRestrito());

        // ehRecorte = pertencimento (só Mediuns/DEPAE/Direcionada) — conceito distinto (R3).
        $this->assertFalse(VisibilidadeMensagem::Trabalhadores->ehRecorte()); // difere de ehRestrito aqui
        $this->assertTrue(VisibilidadeMensagem::Mediuns->ehRecorte());
    }

    public function test_paleta_tem_hue_fundo_e_texto_por_nivel(): void
    {
        foreach (VisibilidadeMensagem::cases() as $v) {
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $v->cor(), $v->value);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $v->corTexto(), $v->value);
            $this->assertStringStartsWith('rgba(', $v->corFundo());
        }
    }
}
