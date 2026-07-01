<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestrantesListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_mostra_ativos_e_oculta_inativos(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);

        $resp = $this->get(route('palestrantes.index'));

        $resp->assertOk();
        $resp->assertSee('João Ativo');
        $resp->assertDontSee('Maria Inativa');
    }

    public function test_index_tem_hero_stats_e_jsonld(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Wagner Alberto']);

        $resp = $this->get(route('palestrantes.index'));

        $resp->assertOk();
        $resp->assertSee('Palestrantes');                        // H1 do hero
        $resp->assertSee('Wagner Alberto');                       // grade (livewire)
        $resp->assertSee('Colaboradores');                        // stat 1
        $resp->assertSee('Palestras no acervo');                  // stat 2
        $resp->assertSee('"@type":"BreadcrumbList"', false);      // JSON-LD
    }

    public function test_index_destaque_some_sem_proxima_e_aparece_com(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Wagner Alberto']);

        // sem palestra futura → "Em destaque" não aparece (sem fallback)
        $this->get(route('palestrantes.index'))->assertDontSee('Em destaque');

        Palestra::factory()->create([
            'titulo' => 'Palestra Bem Futura',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestrantes.index'));
        $resp->assertSee('Em destaque');
        $resp->assertSee('Palestra Bem Futura');
    }
}
