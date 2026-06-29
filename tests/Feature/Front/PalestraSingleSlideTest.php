<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleSlideTest extends TestCase
{
    use RefreshDatabase;

    public function test_botao_baixar_slides_aparece_quando_preenchido(): void
    {
        Palestra::factory()->create([
            'slug' => 'com-slide',
            'status' => Palestra::STATUS_PUBLICADO,
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
        ]);

        $resp = $this->get(route('palestras.show', 'com-slide'));

        $resp->assertOk();
        $resp->assertSee('Baixar slides');
        $resp->assertSee('https://drive.google.com/uc?export=download&id=1ABCdefg_hij');
    }

    public function test_botao_baixar_slides_oculto_sem_slide(): void
    {
        Palestra::factory()->create(['slug' => 'sem-slide', 'status' => Palestra::STATUS_PUBLICADO, 'slide' => null]);

        $this->get(route('palestras.show', 'sem-slide'))->assertOk()->assertDontSee('Baixar slides');
    }

    public function test_video_em_breve_quando_sem_link(): void
    {
        Palestra::factory()->create(['slug' => 'sem-video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $resp = $this->get(route('palestras.show', 'sem-video'));

        $resp->assertOk();
        $resp->assertSee('Vídeo em breve');
        $resp->assertDontSee('youtube.com/embed', false); // não carrega iframe no load
    }

    public function test_referencias_doutrinarias_e_evangelicas_renderizam(): void
    {
        $palestra = Palestra::factory()->create([
            'slug' => 'com-refs',
            'status' => Palestra::STATUS_PUBLICADO,
            'referencias_evangelicas' => 'A promessa do Consolador (João 14).',
        ]);
        $palestra->referencias()->create(['obra' => 'O Livro dos Espíritos', 'autor' => 'Allan Kardec', 'nota' => 'Progresso moral.', 'ordem' => 0]);

        $resp = $this->get(route('palestras.show', 'com-refs'));

        $resp->assertOk();
        $resp->assertSee('Referências doutrinárias');
        $resp->assertSee('O Livro dos Espíritos');
        $resp->assertSee('Allan Kardec');
        $resp->assertSee('Referências evangélicas');
        $resp->assertSee('A promessa do Consolador (João 14).');
    }

    public function test_relacionadas_renderizam_no_html(): void
    {
        $assunto = \App\Models\Assunto::factory()->create();
        $atual = Palestra::factory()->create(['slug' => 'atual-r', 'status' => Palestra::STATUS_PUBLICADO]);
        $atual->assuntos()->attach($assunto);
        $irma = Palestra::factory()->create(['titulo' => 'Palestra Irmã', 'status' => Palestra::STATUS_PUBLICADO]);
        $irma->assuntos()->attach($assunto);

        $this->get(route('palestras.show', 'atual-r'))->assertOk()->assertSee('Palestra Irmã');
    }
}
