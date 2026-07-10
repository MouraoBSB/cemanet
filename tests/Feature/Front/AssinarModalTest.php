<?php

namespace Tests\Feature\Front;

use Tests\TestCase;

class AssinarModalTest extends TestCase
{
    public function test_modal_monta_links_google_apple_e_download(): void
    {
        $feed = 'http://localhost/palestra_publica/calendario.ics';

        $view = $this->blade('<x-ui.assinar-modal :feeds="$feeds" />', ['feeds' => [['rotulo' => 'Palestras', 'url' => $feed]]]);

        // webcal (Apple) — mesmo host/path do feed
        $view->assertSee('webcal://localhost/palestra_publica/calendario.ics', false);
        // Google Calendar por URL
        $view->assertSee('calendar.google.com/calendar/r', false);
        // Baixar .ics (attachment)
        $view->assertSee('palestra_publica/calendario.ics?download=1', false);
        // acessibilidade do dialog
        $view->assertSee('role="dialog"', false);
        $view->assertSee('aria-modal="true"', false);
    }
}
