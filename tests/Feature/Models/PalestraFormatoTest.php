<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraFormatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_gera_rotulo_e_cor(): void
    {
        $palestra = Palestra::factory()->make(['online' => true]);

        $this->assertSame('Online', $palestra->formato['rotulo']);
        $this->assertSame('secondary', $palestra->formato['cor']);
    }

    public function test_presencial_gera_rotulo_e_cor(): void
    {
        $palestra = Palestra::factory()->make(['online' => false]);

        $this->assertSame('Presencial', $palestra->formato['rotulo']);
        $this->assertSame('accent', $palestra->formato['cor']);
    }
}
