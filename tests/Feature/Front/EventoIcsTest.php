<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoIcsTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $o = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó', 'slug' => 'brecho', 'data_inicio' => Carbon::now()->addDays(20)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_feed_agregado_so_publicos_nao_encerrados(): void
    {
        $this->evento(['slug' => 'futuro-publico', 'titulo' => 'Feirão Aberto']);
        $this->evento([
            'slug' => 'futuro-restrito', 'titulo' => 'Reunião Reservada',
            'visibilidade' => VisibilidadeEvento::Diretoria,
        ]);
        $this->evento([
            'slug' => 'passado-publico', 'titulo' => 'Evento Encerrado',
            'data_inicio' => Carbon::now()->subDays(20)->toDateString(),
        ]);

        $r = $this->get('/eventos/calendario.ics')->assertOk();

        $this->assertStringContainsString('text/calendar', (string) $r->headers->get('Content-Type'));
        $r->assertSee('BEGIN:VCALENDAR', false);
        $r->assertSee('Feirão Aberto', false);           // público futuro entra
        $r->assertDontSee('Reunião Reservada', false);   // restrito fica de fora
        $r->assertDontSee('Evento Encerrado', false);    // encerrado fica de fora
    }

    public function test_feed_com_download_forca_anexo(): void
    {
        $this->evento(['slug' => 'futuro-publico']);

        $r = $this->get('/eventos/calendario.ics?download=1')->assertOk();

        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('cema-eventos.ics', (string) $r->headers->get('Content-Disposition'));
    }

    public function test_ics_de_evento_restrito_404_para_anonimo(): void
    {
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $this->get('/eventos/reservado/calendario.ics')->assertNotFound(); // 404, não 403
    }

    public function test_ics_de_evento_restrito_visivel_a_diretor_com_cache_privado(): void
    {
        $this->evento(['slug' => 'reservado', 'titulo' => 'Reunião', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $r = $this->actingAs($this->diretor())->get('/eventos/reservado/calendario.ics')->assertOk();

        $this->assertStringContainsString('text/calendar', (string) $r->headers->get('Content-Type'));
        $this->assertStringContainsString('private', (string) $r->headers->get('Cache-Control'));
        $r->assertSee('Reunião', false);
    }

    public function test_ics_de_evento_publico_nao_marca_cache_privado(): void
    {
        $this->evento(['slug' => 'brecho']);

        $r = $this->get('/eventos/brecho/calendario.ics')->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }
}
