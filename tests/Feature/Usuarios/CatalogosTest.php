<?php

namespace Tests\Feature\Usuarios;

use App\Models\Departamento;
use App\Models\Setor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    public function test_setor_pertence_a_departamento_e_pode_ser_sem_departamento(): void
    {
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);
        $comDepto = Setor::create(['nome' => 'Médium', 'slug' => 'medium', 'departamento_id' => $depto->id]);
        $pamana = Setor::create(['nome' => 'PAMANA', 'slug' => 'pamana', 'departamento_id' => null]);

        $this->assertSame('DEPAE', $comDepto->departamento->sigla);
        $this->assertNull($pamana->departamento);
    }
}
