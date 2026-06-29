<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Curtir;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurtirPalestraTest extends TestCase
{
    use RefreshDatabase;

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
}
