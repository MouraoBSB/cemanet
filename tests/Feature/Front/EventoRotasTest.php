<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoRotasTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $o = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó', 'slug' => 'brecho', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    public function test_archive_200(): void
    {
        $this->evento();
        $this->get('/eventos')->assertOk()->assertSee('Eventos');
    }

    public function test_single_publico_200(): void
    {
        $this->evento();
        $this->get('/eventos/brecho')->assertOk()->assertSee('Brechó');
    }

    public function test_single_restrito_404_para_anonimo(): void
    {
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria]);
        $this->get('/eventos/reservado')->assertNotFound(); // 404, não 403
    }

    public function test_single_restrito_visivel_para_diretor(): void
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria, 'titulo' => 'Reunião']);

        $this->actingAs($u)->get('/eventos/reservado')->assertOk()->assertSee('Reunião');
    }

    public function test_301_do_legado(): void
    {
        $this->evento();
        $this->get('/_evento')->assertRedirect('/eventos');
        $this->get('/_evento/brecho')->assertStatus(301);
    }
}
