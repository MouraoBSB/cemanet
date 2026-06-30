<?php

namespace Tests\Unit\Palestras;

use App\Support\Palestras\DuracaoPalestra;
use PHPUnit\Framework\TestCase;

class DuracaoPalestraTest extends TestCase
{
    public function test_horas_e_minutos_juntos(): void
    {
        $this->assertSame(70, DuracaoPalestra::minutos('≈1h10'));
        $this->assertSame(150, DuracaoPalestra::minutos('2h30'));
        $this->assertSame(75, DuracaoPalestra::minutos('1h 15'));
    }

    public function test_so_horas(): void
    {
        $this->assertSame(60, DuracaoPalestra::minutos('1h'));
        $this->assertSame(120, DuracaoPalestra::minutos('2h'));
    }

    public function test_so_minutos(): void
    {
        $this->assertSame(45, DuracaoPalestra::minutos('45 min'));
        $this->assertSame(50, DuracaoPalestra::minutos('50min'));
    }

    public function test_fallback_90_para_vazio_nulo_ou_nao_parseavel(): void
    {
        $this->assertSame(90, DuracaoPalestra::minutos(null));
        $this->assertSame(90, DuracaoPalestra::minutos(''));
        $this->assertSame(90, DuracaoPalestra::minutos('   '));
        $this->assertSame(90, DuracaoPalestra::minutos('a confirmar'));
    }
}
