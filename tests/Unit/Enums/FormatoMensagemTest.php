<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Unit\Enums;

use App\Enums\FormatoMensagem;
use PHPUnit\Framework\TestCase;

class FormatoMensagemTest extends TestCase
{
    public function test_tem_os_tres_formatos(): void
    {
        $values = array_map(fn (FormatoMensagem $f) => $f->value, FormatoMensagem::cases());
        $this->assertSame(['psicografia', 'psicofonia', 'pictografia'], $values);
    }

    public function test_opcoes_mapeia_value_para_rotulo(): void
    {
        $opcoes = FormatoMensagem::opcoes();
        $this->assertSame('Psicografia', $opcoes['psicografia']);
        $this->assertSame('Psicofonia', $opcoes['psicofonia']);
        $this->assertSame('Pictografia', $opcoes['pictografia']);
    }
}
