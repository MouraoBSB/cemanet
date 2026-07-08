<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Filament;

use App\Filament\Resources\Eventos\Pages\CreateEvento;
use App\Models\CategoriaEvento;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('administrador', 'web');
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_cria_evento_com_categoria_e_departamentos(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $dep = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Brechó de Junho',
                'slug' => 'brecho-de-junho',
                'data_inicio' => '2026-06-27',
                'categoria_evento_id' => $cat->id,
                'departamentos' => [$dep->id],
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $evento = Evento::firstWhere('slug', 'brecho-de-junho');
        $this->assertNotNull($evento);
        $this->assertSame($cat->id, $evento->categoria_evento_id);
        $this->assertTrue($evento->departamentos->contains($dep));
    }

    public function test_bloqueia_data_fim_anterior_a_inicio(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Evento inválido',
                'slug' => 'evento-invalido',
                'data_inicio' => '2026-06-27',
                'data_fim' => '2026-06-25',
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasFormErrors(['data_fim']);
    }

    public function test_bloqueia_hora_fim_antes_no_mesmo_dia(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Hora invertida',
                'slug' => 'hora-invertida',
                'data_inicio' => '2026-06-27',
                'hora_inicio' => '10:00',
                'hora_fim' => '09:00',
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasFormErrors(['hora_fim']);
    }

    public function test_cria_evento_com_horas_validas_e_periodo_correto(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Encontro com horário',
                'slug' => 'encontro-com-horario',
                'data_inicio' => '2026-06-27',
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $evento = Evento::firstWhere('slug', 'encontro-com-horario');
        $this->assertSame('08:00', $evento->hora_inicio);
        $this->assertSame('12:00', $evento->hora_fim);
        $this->assertSame('27 de junho de 2026 · 8h – 12h', $evento->periodo);
    }
}
