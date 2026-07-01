<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraCardMultiPalestranteTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_mostra_os_dois_palestrantes_ativos(): void
    {
        $palestra = Palestra::factory()->create(['titulo' => 'Palestra a Dois', 'status' => Palestra::STATUS_PUBLICADO]);
        $ana = Palestrante::factory()->ativo()->create(['nome' => 'Ana Prado', 'slug' => 'ana-prado']);
        $bruno = Palestrante::factory()->ativo()->create(['nome' => 'Bruno Lima', 'slug' => 'bruno-lima']);
        $palestra->palestrantes()->attach($ana, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($bruno, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSee('Ana Prado');
        $resp->assertSee('Bruno Lima'); // ambos aparecem no card
    }
}
