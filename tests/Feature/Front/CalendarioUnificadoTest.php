<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioUnificadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendario_200_e_canonical(): void
    {
        $this->get('/calendario')->assertOk()->assertSee('rel="canonical"', false);
    }

    public function test_301_da_url_antiga(): void
    {
        $this->get('/palestra_publica/calendario')->assertRedirect('/calendario');
        $this->get('/palestra_publica/calendario')->assertStatus(301);
    }

    public function test_jsonld_nao_inclui_evento_restrito(): void
    {
        Evento::create(['titulo' => 'Reunião Secreta', 'slug' => 'rs', 'data_inicio' => Carbon::now()->addDays(5)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Feirão Aberto', 'slug' => 'fa', 'data_inicio' => Carbon::now()->addDays(6)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);

        $r = $this->get('/calendario')->assertOk();
        $r->assertSee('Feirão Aberto', false);
        $r->assertDontSee('Reunião Secreta', false); // nem no JSON-LD nem na grade p/ anônimo
    }

    public function test_logado_recebe_cache_control_sem_public(): void
    {
        $r = $this->actingAs(User::factory()->create())->get('/calendario')->assertOk();
        $this->assertStringNotContainsString('public', (string) $r->headers->get('Cache-Control'));
    }

    public function test_sitemap_inclui_calendario(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/calendario'), false);
    }
}
