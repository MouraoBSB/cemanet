<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Curtir;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CurtirPalestraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // isola o estado do RateLimiter entre os testes
    }

    public function test_curtir_incrementa_e_descurtir_decrementa_atomicamente(): void
    {
        $palestra = Palestra::factory()->create(['curtidas' => 0]);

        Livewire::test(Curtir::class, ['palestra' => $palestra])
            ->assertSet('curtidas', 0)
            ->call('curtir')
            ->assertSet('curtidas', 1)
            ->call('descurtir')
            ->assertSet('curtidas', 0);

        $this->assertSame(0, $palestra->refresh()->curtidas);
    }

    public function test_descurtir_nao_passa_de_zero(): void
    {
        $palestra = Palestra::factory()->create(['curtidas' => 0]);

        Livewire::test(Curtir::class, ['palestra' => $palestra])
            ->call('descurtir')
            ->assertSet('curtidas', 0);
    }

    public function test_rate_limiter_limita_curtidas_por_navegador(): void
    {
        $palestra = Palestra::factory()->create(['curtidas' => 0]);
        $componente = Livewire::test(Curtir::class, ['palestra' => $palestra]);

        for ($i = 0; $i < 25; $i++) {
            $componente->call('curtir');
        }

        // limite de 20 tentativas/60s por IP+palestra → no máximo 20 incrementos
        $this->assertSame(20, $palestra->refresh()->curtidas);
    }
}
