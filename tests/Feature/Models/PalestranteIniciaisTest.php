<?php

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Tests\TestCase;

class PalestranteIniciaisTest extends TestCase
{
    public function test_pega_iniciais_das_duas_primeiras_palavras(): void
    {
        $this->assertSame('KM', (new Palestrante(['nome' => 'Kátia Malaquias']))->iniciais);
    }

    public function test_nome_de_uma_palavra_gera_uma_letra(): void
    {
        $this->assertSame('W', (new Palestrante(['nome' => 'Wagner']))->iniciais);
    }

    public function test_ignora_espacos_extras_e_pega_so_as_duas_primeiras(): void
    {
        $this->assertSame('AB', (new Palestrante(['nome' => '  Ana   Beatriz Costa ']))->iniciais);
    }

    public function test_nome_vazio_gera_interrogacao(): void
    {
        $this->assertSame('?', (new Palestrante(['nome' => '   ']))->iniciais);
    }
}
