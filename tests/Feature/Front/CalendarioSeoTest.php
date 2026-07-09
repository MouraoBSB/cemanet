<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_emite_jsonld_itemlist_de_event(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura SEO',
            'online' => false,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(4)->setTime(19, 0),
        ]);

        $resp = $this->get('/calendario');

        $resp->assertOk();
        $resp->assertSee('"@type":"ItemList"', false);
        $resp->assertSee('"@type":"Event"', false);
        $resp->assertSee('"startDate"', false);
        $resp->assertSee('Futura SEO', false);
    }
}
