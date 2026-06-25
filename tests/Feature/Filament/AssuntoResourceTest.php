<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace Tests\Feature\Filament;

use App\Filament\Resources\Assuntos\Pages\CreateAssunto;
use App\Models\Assunto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssuntoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_assunto_com_pai(): void
    {
        $pai = Assunto::factory()->create(['nome' => 'Espiritismo']);

        Livewire::test(CreateAssunto::class)
            ->fillForm([
                'nome' => 'Mediunidade',
                'slug' => 'mediunidade',
                'parent_id' => $pai->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $filho = Assunto::where('slug', 'mediunidade')->first();
        $this->assertTrue($filho->parent->is($pai));
    }
}
