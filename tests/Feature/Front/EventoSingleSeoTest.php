<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoSingleSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_tem_jsonld_event_e_google_calendar(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        Evento::create([
            'titulo' => 'Brechó Solidário', 'slug' => 'brecho', 'resumo' => '<p>Venha</p>',
            'data_inicio' => '2026-06-27', 'hora_inicio' => '08:30',
            'categoria_evento_id' => $cat->id, 'visibilidade' => VisibilidadeEvento::Publico,
            'status' => Evento::STATUS_PUBLICADO, 'local' => 'CEMA',
        ]);

        $r = $this->get('/eventos/brecho')->assertOk();
        $r->assertSee('schema.org', false);
        $r->assertSee('"@type":"Event"', false);
        $r->assertSee('"startDate"', false);
        $r->assertSee('"endDate"', false);        // fim via fimUtc (3b)
        $r->assertSee('calendar.google.com/calendar/render', false);
        $r->assertSee('Serviço');                       // bloco de serviço
        $r->assertSee(config('cema.endereco'));          // endereço da fonte única
        // meta description = resumo em texto puro (sem HTML)
        $r->assertSee('name="description"', false);
        $r->assertDontSee('<p>Venha</p>', false);
    }

    public function test_jsonld_dia_inteiro_multidia_usa_datas_de_inicio_e_fim(): void
    {
        Evento::create([
            'titulo' => 'Semana da Fraternidade', 'slug' => 'semana-fraternidade',
            'data_inicio' => '2026-06-27', 'data_fim' => '2026-06-29', // sem hora → dia inteiro
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ]);

        $r = $this->get('/eventos/semana-fraternidade')->assertOk();
        $r->assertSee('"startDate":"2026-06-27"', false);
        $r->assertSee('"endDate":"2026-06-29"', false); // último dia real, não início+2h
    }

    public function test_single_sem_resumo_usa_descricao_padrao_do_site(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Palestra', 'slug' => 'palestra', 'cor' => '#89AB98']);
        Evento::create([
            'titulo' => 'Encontro Sem Resumo', 'slug' => 'encontro-sem-resumo', 'resumo' => null,
            'data_inicio' => '2026-06-27', 'hora_inicio' => '08:30',
            'categoria_evento_id' => $cat->id, 'visibilidade' => VisibilidadeEvento::Publico,
            'status' => Evento::STATUS_PUBLICADO, 'local' => 'CEMA',
        ]);

        $r = $this->get('/eventos/encontro-sem-resumo')->assertOk();
        $r->assertDontSee('name="description" content=""', false);
        $r->assertSee('Centro Espírita Maria Madalena', false);
    }
}
