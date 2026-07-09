<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_mostra_destaque_e_breadcrumb(): void
    {
        Evento::create([
            'titulo' => 'Próximo Brechó', 'slug' => 'proximo-brecho',
            'data_inicio' => now()->addDays(3)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ]);

        $r = $this->get('/eventos')->assertOk();
        $r->assertSee('Próximo destaque');           // bloco de destaque presente
        $r->assertSee('Próximo Brechó');             // o evento futuro é o destaque
        $r->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_destaque_some_sem_evento_futuro(): void
    {
        Evento::create([
            'titulo' => 'Antigo', 'slug' => 'antigo', 'data_inicio' => now()->subDays(10)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ]);

        $this->get('/eventos')->assertOk()->assertDontSee('Próximo destaque');
    }
}
