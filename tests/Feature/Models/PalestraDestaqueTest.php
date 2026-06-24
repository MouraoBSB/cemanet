<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\PalestraDestaque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraDestaqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_destaques_vem_ordenados(): void
    {
        $palestra = Palestra::factory()->create();
        PalestraDestaque::create(['palestra_id' => $palestra->id, 'destaque' => 'B', 'texto' => 't', 'ordem' => 1]);
        PalestraDestaque::create(['palestra_id' => $palestra->id, 'destaque' => 'A', 'texto' => 't', 'ordem' => 0]);

        $this->assertSame(['A', 'B'], $palestra->destaques->pluck('destaque')->all());
    }
}
