<?php

namespace Tests\Unit\Palestras;

use App\Support\Palestras\CardinalidadePalestra;
use PHPUnit\Framework\TestCase;

class CardinalidadePalestraTest extends TestCase
{
    public function test_um_ou_dois_palestrantes_e_ate_um_diretor_e_valido(): void
    {
        $this->assertSame([], CardinalidadePalestra::erros(1, 0));
        $this->assertSame([], CardinalidadePalestra::erros(2, 1));
    }

    public function test_zero_palestrantes_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(0, 0));
    }

    public function test_tres_palestrantes_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(3, 0));
    }

    public function test_dois_diretores_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(1, 2));
    }
}
