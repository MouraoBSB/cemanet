<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_inclui_publico_e_exclui_restrito(): void
    {
        Evento::create(['titulo' => 'Pub', 'slug' => 'pub-ev', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Rest', 'slug' => 'rest-ev', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);

        $r = $this->get('/sitemap.xml')->assertOk();
        $r->assertSee(route('eventos.index'), false);
        $r->assertSee('/eventos/pub-ev', false);
        $r->assertDontSee('/eventos/rest-ev', false);   // restrito fora do sitemap
    }
}
