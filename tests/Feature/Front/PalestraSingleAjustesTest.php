<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleAjustesTest extends TestCase
{
    use RefreshDatabase;

    public function test_descricao_e_justificada(): void
    {
        Palestra::factory()->create([
            'slug' => 'desc-just',
            'status' => Palestra::STATUS_PUBLICADO,
            'descricao' => '<p>Texto da descrição da palestra.</p>',
        ]);

        $resp = $this->get(route('palestras.show', 'desc-just'));

        $resp->assertOk();
        $resp->assertSee('text-justify', false); // bloco da descrição usa justificado
    }

    public function test_nao_mostra_link_baixar_ics(): void
    {
        // factory preenche data_da_palestra → o botão "Adicionar ao calendário" aparece,
        // mas o link redundante "Baixar .ics" não deve mais existir.
        Palestra::factory()->create(['slug' => 'sem-ics', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.show', 'sem-ics'));

        $resp->assertOk();
        $resp->assertSee('Adicionar ao calendário');
        $resp->assertDontSee('Baixar .ics');
    }

    public function test_relacionadas_mostram_thumb_do_youtube(): void
    {
        $assunto = Assunto::factory()->create();
        // atual SEM vídeo (player não emite i.ytimg.com), para isolar a thumb da relacionada
        $atual = Palestra::factory()->create(['slug' => 'atual-thumb', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);
        $atual->assuntos()->attach($assunto);
        $irma = Palestra::factory()->create([
            'slug' => 'irma-thumb', 'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtube.com/live/ABCdefg',
        ]);
        $irma->assuntos()->attach($assunto);

        $resp = $this->get(route('palestras.show', 'atual-thumb'));

        $resp->assertOk();
        // o mini-card da relacionada renderiza a thumb do YouTube (hqdefault, decisão da archive) — não só o gradiente
        $resp->assertSee('i.ytimg.com/vi/ABCdefg/hqdefault.jpg', false);
    }
}
