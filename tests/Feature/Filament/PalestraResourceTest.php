<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Palestras\Pages\CreatePalestra;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestraResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_palestra_com_assuntos_destaques_e_um_palestrante(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();
        $assunto = Assunto::factory()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Auxílios do Invisível',
                'slug' => 'auxilios-do-invisivel',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'assuntos' => [$assunto->id],
                'destaques' => [
                    ['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'auxilios-do-invisivel')->first();
        $this->assertNotNull($palestra);
        $this->assertTrue($palestra->assuntos->contains($assunto));
        $this->assertCount(1, $palestra->destaques);
    }

    public function test_lista_renderiza(): void
    {
        Palestra::factory()->count(3)->create();

        $this->get('/admin/palestras')->assertOk();
    }
}
