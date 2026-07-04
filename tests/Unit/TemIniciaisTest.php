<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Unit;

use App\Models\Palestrante;
use App\Models\User;
use Tests\TestCase;

class TemIniciaisTest extends TestCase
{
    public function test_iniciais_do_usuario_de_name(): void
    {
        $this->assertSame('TM', (new User(['name' => 'Thiago Mourão']))->iniciais);
        $this->assertSame('A', (new User(['name' => 'Ana']))->iniciais);
        $this->assertSame('?', (new User(['name' => '']))->iniciais);
        $this->assertSame('MC', (new User(['name' => '  maria   clara  ']))->iniciais);
    }

    public function test_iniciais_do_palestrante_de_nome_inalterada(): void
    {
        $p = new Palestrante(['nome' => 'João da Silva']);
        $this->assertSame('JD', $p->iniciais);
    }
}
