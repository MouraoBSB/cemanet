<?php

namespace Tests\Feature\Front;

use App\Models\Categoria;
use App\Models\Configuracao;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_retorna_200(): void
    {
        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
    }

    public function test_listagem_mostra_post_publicado(): void
    {
        Post::factory()->create([
            'titulo'  => 'Artigo Publicado',
            'status'  => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDay(),
        ]);

        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
        $resp->assertSee('Artigo Publicado');
    }

    public function test_listagem_nao_mostra_rascunho(): void
    {
        Post::factory()->create([
            'titulo' => 'Rascunho Secreto',
            'status' => Post::STATUS_RASCUNHO,
        ]);

        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
        $resp->assertDontSee('Rascunho Secreto');
    }

    public function test_listagem_nao_mostra_post_com_data_futura(): void
    {
        Post::factory()->create([
            'titulo'          => 'Post Futuro',
            'status'          => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->addDays(3),
        ]);

        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
        $resp->assertDontSee('Post Futuro');
    }

    public function test_mais_lidas_ordena_por_visualizacoes(): void
    {
        Post::factory()->create([
            'titulo'        => 'Post Pouco Lido',
            'visualizacoes' => 10,
            'status'        => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDays(2),
        ]);
        Post::factory()->create([
            'titulo'        => 'Post Muito Lido',
            'visualizacoes' => 999,
            'status'        => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDays(3),
        ]);
        Post::factory()->create([
            'titulo'        => 'Post Medianamente Lido',
            'visualizacoes' => 250,
            'status'        => Post::STATUS_PUBLICADO,
            'data_publicacao' => now()->subDays(4),
        ]);

        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
        $resp->assertSeeInOrder([
            'Post Muito Lido',
            'Post Medianamente Lido',
            'Post Pouco Lido',
        ]);
    }

    public function test_reflexao_do_dia_configurada_aparece_na_listagem(): void
    {
        Configuracao::definir('blog.reflexao_do_dia', 'Semeia o bem sem esperar a colheita.');

        $resp = $this->get(route('blog.index'));

        $resp->assertOk();
        $resp->assertSee('Semeia o bem sem esperar a colheita.');
    }
}
