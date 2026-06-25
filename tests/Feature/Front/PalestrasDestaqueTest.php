<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestrasDestaqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_destaca_proxima_palestra_futura(): void
    {
        Palestra::factory()->create(['titulo' => 'Já Passou', 'data_da_palestra' => Carbon::now()->subDays(10), 'status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['titulo' => 'Vem Aí', 'data_da_palestra' => Carbon::now()->addDays(5), 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSeeText('Próximas Palestras');
        $resp->assertSeeText('Vem Aí');
    }

    public function test_destaque_futuro_tem_contagem_regressiva(): void
    {
        Palestra::factory()->create(['titulo' => 'Vem Aí', 'data_da_palestra' => Carbon::now()->addDays(5), 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSee('Contagem regressiva para a palestra', false); // timer Alpine no destaque
    }

    public function test_passada_nao_tem_contagem_regressiva(): void
    {
        Palestra::factory()->create(['titulo' => 'Só Passado', 'data_da_palestra' => Carbon::now()->subDays(10), 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertDontSee('Contagem regressiva para a palestra', false); // sem futura → sem timer
    }
}
