<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioStubTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_calendario_responde_200(): void
    {
        $this->get('/palestra_publica/calendario')
            ->assertOk()
            ->assertSee('Calendário de Palestras');
    }

    public function test_rota_nomeada_aponta_para_a_pagina(): void
    {
        $this->assertSame(url('/palestra_publica/calendario'), route('palestras.calendario'));
    }

    public function test_single_ainda_responde_com_slug(): void
    {
        Palestra::factory()->create(['slug' => 'uma-palestra', 'status' => Palestra::STATUS_PUBLICADO]);

        $this->get('/palestra_publica/uma-palestra')->assertOk();
    }

    public function test_calendario_lista_palestra_futura(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra Futura',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(3),
        ]);

        $this->get('/palestra_publica/calendario')->assertSee('Palestra Futura');
    }
}
