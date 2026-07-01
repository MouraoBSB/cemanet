<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioRotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rota_feed_resolve_para_a_url_esperada(): void
    {
        $this->assertSame(url('/palestra_publica/calendario.ics'), route('palestras.calendario-ics'));
    }

    public function test_feed_nao_e_capturado_pelo_show(): void
    {
        // Não existe palestra com slug 'calendario.ics'; o ponto do ".ics" deve cair no feed, não no {slug}.
        $resp = $this->get('/palestra_publica/calendario.ics');

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
    }

    public function test_pagina_calendario_segue_respondendo_200(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => now()->addDays(3),
        ]);

        $this->get('/palestra_publica/calendario')->assertOk();
    }
}
