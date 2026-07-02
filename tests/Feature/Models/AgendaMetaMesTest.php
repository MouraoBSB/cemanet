<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaMetaMes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaMetaMesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_meta_mes_com_casts_inteiros(): void
    {
        $meta = AgendaMetaMes::create([
            'ano' => 2026,
            'mes' => 6,
            'titulo' => 'Combater o egoísmo: indiferença e ingratidão',
        ]);

        $this->assertSame(2026, $meta->fresh()->ano);
        $this->assertSame(6, $meta->fresh()->mes);
        $this->assertDatabaseHas('agenda_metas_mes', ['ano' => 2026, 'mes' => 6]);
    }

    public function test_par_ano_mes_e_unico(): void
    {
        AgendaMetaMes::create(['ano' => 2026, 'mes' => 6, 'titulo' => 'Primeiro']);

        $this->expectException(QueryException::class);

        AgendaMetaMes::create(['ano' => 2026, 'mes' => 6, 'titulo' => 'Duplicado']);
    }

    public function test_factory_gera_registro_valido(): void
    {
        $meta = AgendaMetaMes::factory()->create(['ano' => 2026, 'mes' => 8]);

        $this->assertSame(2026, $meta->fresh()->ano);
        $this->assertSame(8, $meta->fresh()->mes);
    }
}
