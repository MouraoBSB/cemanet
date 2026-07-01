<?php

namespace Tests\Feature\Front;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestranteCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_foto_renderiza_iniciais_e_gradiente_por_indice(): void
    {
        // slug explícito (sem número) para que o '12' asserido seja só o do badge, não o do slug.
        $p = Palestrante::factory()->create(['nome' => 'Divino Gabriel', 'slug' => 'divino-gabriel']);
        $p->palestras_ministradas_count = 12; // atributo dinâmico (alias do render) p/ o badge

        $view = $this->blade('<x-palestrante.card :palestrante="$p" />', ['p' => $p]);

        $view->assertSee('DG', false);                       // iniciais (fallback)
        $view->assertSee('cema-grad-'.($p->id % 8), false);  // gradiente rotacionado por índice
        $view->assertSee('12', false);                        // badge de contagem
        $view->assertSee(route('palestrantes.show', $p->slug), false); // link para o perfil
        $view->assertDontSee('<img', false);                  // sem foto → não emite <img>
    }

    public function test_badge_zero_quando_sem_contagem(): void
    {
        $p = Palestrante::factory()->create(['nome' => 'Ana Sem Palestra']);

        $view = $this->blade('<x-palestrante.card :palestrante="$p" />', ['p' => $p]);

        $view->assertSee('Ana Sem Palestra', false);
        $view->assertSee('Ver palestras', false);
    }
}
