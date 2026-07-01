<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_responde_text_calendar_com_futuras(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura A',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
        $resp->assertSee('BEGIN:VCALENDAR', false);
        $resp->assertSee('SUMMARY:Futura A', false);
        $this->assertSame(1, substr_count($resp->getContent(), 'BEGIN:VEVENT'));
    }

    public function test_feed_exclui_passadas(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura A',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);
        Palestra::factory()->create([
            'titulo' => 'Passada B',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->subDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $resp->assertSee('SUMMARY:Futura A', false);
        $resp->assertDontSee('Passada B', false);
    }

    public function test_feed_inline_por_padrao(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $this->assertStringNotContainsString('attachment', (string) $resp->headers->get('content-disposition'));
    }

    public function test_feed_download_adiciona_attachment(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics', ['download' => 1]));

        $this->assertStringContainsString('attachment', $resp->headers->get('content-disposition'));
        $this->assertStringContainsString('cema-palestras.ics', $resp->headers->get('content-disposition'));
    }
}
