<?php

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PalestranteChamadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coluna_chamada_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('palestrantes', 'chamada'));
    }

    public function test_chamada_e_atribuivel_e_opcional(): void
    {
        $p = Palestrante::factory()->create(['chamada' => 'Trabalhador do bem.']);
        $this->assertSame('Trabalhador do bem.', $p->fresh()->chamada);

        $semChamada = Palestrante::factory()->create(['chamada' => null]);
        $this->assertNull($semChamada->fresh()->chamada);
    }
}
