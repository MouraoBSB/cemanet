<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $p->data_da_palestra);
        $this->assertTrue($p->online);
        $this->assertSame(120, $p->publico_total);
    }

    public function test_escopo_publicado(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['status' => Palestra::STATUS_RASCUNHO]);

        $this->assertCount(1, Palestra::publicado()->get());
    }
}
