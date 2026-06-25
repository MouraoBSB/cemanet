<?php

namespace Tests\Unit\Importacao;

use App\Importacao\TransformadorLegado;
use PHPUnit\Framework\TestCase;

class TransformadorLegadoTest extends TestCase
{
    public function test_status_para_ativo(): void
    {
        $this->assertTrue(TransformadorLegado::statusParaAtivo('true'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('on'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('TRUE'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('1'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('sim'));
        $this->assertFalse(TransformadorLegado::statusParaAtivo('false'));
        $this->assertFalse(TransformadorLegado::statusParaAtivo(''));
        $this->assertFalse(TransformadorLegado::statusParaAtivo(null));
    }

    public function test_unix_para_data_no_fuso_de_brasilia(): void
    {
        // O legado guarda o horário de parede (19:00) como se fosse UTC.
        // 1782673200 → relógio UTC = 2026-06-28 19:00 → reinterpretado como SP 19:00.
        $data = TransformadorLegado::unixParaData('1782673200');
        $this->assertSame('2026-06-28 19:00:00', $data->format('Y-m-d H:i:s'));
        $this->assertSame('America/Sao_Paulo', $data->timezoneName);
        $this->assertSame('Sunday', $data->format('l'));
        $this->assertNull(TransformadorLegado::unixParaData(null));
        $this->assertNull(TransformadorLegado::unixParaData('0'));
    }

    public function test_destaques_do_repeater(): void
    {
        $serializado = serialize([
            'item-0' => ['destaque' => 'Fé', 'texto' => 'Sobre a fé'],
            'item-1' => ['destaque' => 'Caridade', 'texto' => 'Sobre caridade'],
        ]);
        $destaques = TransformadorLegado::destaquesDoRepeater($serializado);
        $this->assertCount(2, $destaques);
        $this->assertSame(['destaque' => 'Fé', 'texto' => 'Sobre a fé', 'ordem' => 0], $destaques[0]);
        $this->assertSame(1, $destaques[1]['ordem']);

        $this->assertSame([], TransformadorLegado::destaquesDoRepeater(''));
        $this->assertSame([], TransformadorLegado::destaquesDoRepeater(null));
        $this->assertSame([], TransformadorLegado::destaquesDoRepeater('lixo-não-serializado'));
    }
}
