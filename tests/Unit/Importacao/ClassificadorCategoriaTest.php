<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Importacao;

use App\Importacao\ClassificadorCategoria;
use PHPUnit\Framework\TestCase;

class ClassificadorCategoriaTest extends TestCase
{
    public function test_infere_categoria_pelo_titulo(): void
    {
        $this->assertSame('brecho', ClassificadorCategoria::paraSlug('Brechó Solidário do CEMA – Festa Junina'));
        $this->assertSame('feirao', ClassificadorCategoria::paraSlug('Feirão de Livros Espíritas — Chico Xavier'));
        $this->assertSame('feirao', ClassificadorCategoria::paraSlug('Grande venda de Livros usados'));
        $this->assertSame('familia', ClassificadorCategoria::paraSlug('Encontro da Família CEMA'));
        $this->assertSame('familia', ClassificadorCategoria::paraSlug('Semana da Família'));
        $this->assertSame('campanha', ClassificadorCategoria::paraSlug('Campanha do Agasalho 2026'));
        $this->assertSame('estudo', ClassificadorCategoria::paraSlug('Curso de Passe'));
        $this->assertSame('estudo', ClassificadorCategoria::paraSlug('20º CEMART — Estudo do Evangelho'));
    }

    public function test_titulo_desconhecido_retorna_null(): void
    {
        $this->assertNull(ClassificadorCategoria::paraSlug('Reunião de Diretoria'));
        $this->assertNull(ClassificadorCategoria::paraSlug(''));
    }
}
