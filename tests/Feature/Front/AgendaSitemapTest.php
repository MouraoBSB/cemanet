<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contem_url_nua_da_agenda(): void
    {
        $resp = $this->get('/sitemap.xml');

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/xml');
        // <loc> exata evita falso-positivo (as URLs datadas têm a nua como prefixo).
        $resp->assertSee('<loc>'.route('agenda.index').'</loc>', false);
    }

    public function test_sitemap_contem_url_datada_de_dia_publicado(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-20',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get('/sitemap.xml');

        $resp->assertSee('<loc>'.route('agenda.show', '2026-07-20').'</loc>', false);
    }

    public function test_sitemap_nao_contem_dia_em_rascunho(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-21',
            'status' => AgendaDia::STATUS_RASCUNHO,
        ]);

        $resp = $this->get('/sitemap.xml');

        $resp->assertDontSee('<loc>'.route('agenda.show', '2026-07-21').'</loc>', false);
    }
}
