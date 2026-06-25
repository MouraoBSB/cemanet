<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_palestra_com_casts(): void
    {
        $p = Palestra::create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'subtitulo' => 'Precisamos fazer a nossa parte',
            'descricao' => '<p>Corpo</p>',
            'data_da_palestra' => '2026-05-31 19:00:00',
            'online' => true,
            'link_youtube' => 'https://youtube.com/live/abc',
            'cor_fundo' => '#89ab98',
            'publico_total' => 120,
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->assertInstanceOf(Carbon::class, $p->data_da_palestra);
        $this->assertTrue($p->online);
        $this->assertSame(120, $p->publico_total);
    }

    public function test_escopo_publicado(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['status' => Palestra::STATUS_RASCUNHO]);

        $this->assertCount(1, Palestra::publicado()->get());
    }

    public function test_pode_ter_data_nula(): void
    {
        // Nem toda palestra do legado tem data definida (ex.: "Paz e Nós").
        $p = Palestra::create([
            'titulo' => 'Paz e Nós',
            'slug' => 'homem-chamado-amor',
            'data_da_palestra' => null,
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->assertNull($p->fresh()->data_da_palestra);
    }
}
