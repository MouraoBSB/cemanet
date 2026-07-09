<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Enums\VisibilidadeEvento;
use App\Livewire\Calendario\Calendario;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarioLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function semear(): void
    {
        $quando = Carbon::now()->addDays(10);
        Palestra::factory()->create(['status' => 'publicado', 'titulo' => 'Palestra X', 'slug' => 'px', 'data_da_palestra' => $quando->copy()->setTime(19, 0)]);
        Evento::create(['titulo' => 'Evento Y', 'slug' => 'ey', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Reunião Secreta', 'slug' => 'rs', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_todos_intercala_palestra_e_evento_publicos(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)
            ->assertSee('Palestra X')
            ->assertSee('Evento Y')
            ->assertDontSee('Reunião Secreta'); // anônimo não vê restrito
    }

    public function test_filtro_tipo_isola_a_fonte(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)->set('tipo', 'palestras')
            ->assertSee('Palestra X')->assertDontSee('Evento Y');
        Livewire::test(Calendario::class)->set('tipo', 'eventos')
            ->assertSee('Evento Y')->assertDontSee('Palestra X');
    }

    public function test_diretor_ve_evento_restrito(): void
    {
        $this->semear();
        Livewire::actingAs($this->diretor())->test(Calendario::class)
            ->assertSee('Reunião Secreta');
    }
}
