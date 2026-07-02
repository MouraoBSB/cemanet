<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaSlugLegado;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaSlugLegadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_slug_com_cast_de_data(): void
    {
        $slug = AgendaSlugLegado::create([
            'slug' => '02-de-julho-de-2026',
            'data' => '2026-07-02',
        ]);

        $this->assertInstanceOf(Carbon::class, $slug->fresh()->data);
        $this->assertSame('2026-07-02', $slug->fresh()->data->format('Y-m-d'));
    }

    public function test_slug_e_unico(): void
    {
        // slug numérico (maio) = post ID
        AgendaSlugLegado::create(['slug' => '27057', 'data' => '2026-05-01']);

        $this->expectException(QueryException::class);

        AgendaSlugLegado::create(['slug' => '27057', 'data' => '2026-05-02']);
    }

    public function test_varios_slugs_apontam_para_a_mesma_data(): void
    {
        // duplicatas históricas que o Google indexou (N:1)
        AgendaSlugLegado::create(['slug' => '05-de-agosto-de-2026', 'data' => '2026-08-05']);
        AgendaSlugLegado::create(['slug' => '05-de-agosto-de-2026-2', 'data' => '2026-08-05']);

        $this->assertSame(2, AgendaSlugLegado::where('data', '2026-08-05')->count());
    }
}
