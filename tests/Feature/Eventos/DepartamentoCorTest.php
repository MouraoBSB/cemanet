<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentoCorTest extends TestCase
{
    use RefreshDatabase;

    public function test_departamento_persiste_cor_e_icone(): void
    {
        $depto = Departamento::create([
            'sigla' => 'DEPRO', 'nome' => 'Promoções e Eventos', 'slug' => 'depro',
            'cor' => '#4E4483', 'icone' => 'calendar',
        ]);

        $this->assertSame('#4E4483', $depto->fresh()->cor);
        $this->assertSame('calendar', $depto->fresh()->icone);
    }
}
