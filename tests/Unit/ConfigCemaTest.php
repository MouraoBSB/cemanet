<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit;

use Tests\TestCase;

class ConfigCemaTest extends TestCase
{
    public function test_endereco_e_nome_da_sede(): void
    {
        $this->assertSame('Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF', config('cema.endereco'));
        $this->assertSame('Centro Espírita Maria Madalena', config('cema.nome'));
    }
}
