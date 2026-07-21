<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Unit\Mensagens;

use App\Support\Mensagens\RegraPublicacao;
use PHPUnit\Framework\TestCase;

/**
 * Unit puro (sem app Laravel bootado) — mesmo molde de CardinalidadePalestraTest.
 * erros() devolve mensagens, nunca lança (V2): ValidationException::withMessages() depende
 * da facade Validator, que não existe fora do container.
 */
class RegraPublicacaoTest extends TestCase
{
    public function test_nivel_ausente_e_invalido(): void
    {
        $this->assertCount(1, RegraPublicacao::erros(['nivel' => null]));
    }

    public function test_nivel_vazio_e_invalido(): void
    {
        $this->assertCount(1, RegraPublicacao::erros(['nivel' => '']));
    }

    public function test_nivel_desconhecido_e_invalido(): void
    {
        $this->assertCount(1, RegraPublicacao::erros(['nivel' => 'lixo']));
    }

    public function test_nivel_publico_e_valido(): void
    {
        $this->assertSame([], RegraPublicacao::erros(['nivel' => 'publico']));
    }

    public function test_direcionada_sem_destinatario_e_invalido(): void
    {
        $this->assertCount(1, RegraPublicacao::erros(['nivel' => 'direcionada']));
    }

    public function test_direcionada_com_destinatario_e_valido(): void
    {
        $this->assertSame([], RegraPublicacao::erros(['nivel' => 'direcionada', 'destinatarios' => [1]]));
    }
}
