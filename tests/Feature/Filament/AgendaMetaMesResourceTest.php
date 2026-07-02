<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes;
use App\Models\AgendaMetaMes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaMetaMesResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_meta_mes(): void
    {
        Livewire::test(CreateAgendaMetaMes::class)
            ->fillForm([
                'ano' => 2026,
                'mes' => 7,
                'titulo' => 'Combater o egoísmo: inveja, ciúme e maledicência',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('agenda_metas_mes', [
            'ano' => 2026,
            'mes' => 7,
            'titulo' => 'Combater o egoísmo: inveja, ciúme e maledicência',
        ]);
    }

    public function test_rejeita_ano_mes_duplicado(): void
    {
        AgendaMetaMes::factory()->create([
            'ano' => 2026,
            'mes' => 7,
            'titulo' => 'Tema já existente',
        ]);

        Livewire::test(CreateAgendaMetaMes::class)
            ->fillForm([
                'ano' => 2026,
                'mes' => 7,
                'titulo' => 'Outro tema para o mesmo mês',
            ])
            ->call('create')
            ->assertHasFormErrors(['mes']);

        $this->assertDatabaseMissing('agenda_metas_mes', [
            'titulo' => 'Outro tema para o mesmo mês',
        ]);
    }
}
