<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Blog\Lista;
use App\Models\Categoria;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogListaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria 5 posts populares (views altas) que ocupam as "Mais lidas" da sidebar,
     * de modo que posts com views=0 não apareçam nesse bloco.
     */
    private function criarPostsPopularesParaSidebar(): void
    {
        Post::factory()->count(5)->create([
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 9999,
            'data_publicacao' => now()->subYear(),
        ]);
    }

    public function test_filtro_categoria_reduz_lista(): void
    {
        $this->criarPostsPopularesParaSidebar();

        $cat1 = Categoria::factory()->create(['slug' => 'reflexoes-xz', 'nome' => 'Reflexões XZ']);
        $cat2 = Categoria::factory()->create(['slug' => 'pratica-amor-xz', 'nome' => 'Prática do Amor XZ']);

        // postCat1 é o mais recente → será o $destaque global
        $postCat1 = Post::factory()->create([
            'titulo'          => 'Artigo Reflexoes XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(1),
        ]);
        $postCat1->categorias()->attach($cat1);

        // postCat2 mais antigo, views=0 → não entra no destaque nem nas mais lidas
        $postCat2 = Post::factory()->create([
            'titulo'          => 'Artigo Pratica XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(5),
        ]);
        $postCat2->categorias()->attach($cat2);

        // Sem filtro: ambos visíveis
        Livewire::test(Lista::class)
            ->assertSee('Artigo Reflexoes XZ Unico')
            ->assertSee('Artigo Pratica XZ Unico')
            // Com filtro: só cat1 no grid; postCat2 não aparece em nenhuma seção
            ->set('categoria', 'reflexoes-xz')
            ->assertSee('Artigo Reflexoes XZ Unico')
            ->assertDontSee('Artigo Pratica XZ Unico');
    }

    public function test_limpar_categoria_mostra_todos(): void
    {
        $this->criarPostsPopularesParaSidebar();

        $cat = Categoria::factory()->create(['slug' => 'espiritualidade-xz']);

        $postA = Post::factory()->create([
            'titulo'          => 'Artigo Espiritualidade XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(1),
        ]);
        $postA->categorias()->attach($cat);

        // postB sem categoria, views=0, mais antigo → não aparece em destaque nem sidebar
        $postB = Post::factory()->create([
            'titulo'          => 'Artigo Sem Categoria XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(6),
        ]);

        Livewire::test(Lista::class)
            ->set('categoria', 'espiritualidade-xz')
            ->assertSee('Artigo Espiritualidade XZ Unico')
            ->assertDontSee('Artigo Sem Categoria XZ Unico')
            ->set('categoria', '')
            ->assertSee('Artigo Espiritualidade XZ Unico')
            ->assertSee('Artigo Sem Categoria XZ Unico');
    }

    public function test_busca_por_titulo_filtra_posts(): void
    {
        $this->criarPostsPopularesParaSidebar();

        Post::factory()->create([
            'titulo'          => 'Fe Raciocinada XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(1),
        ]);
        Post::factory()->create([
            'titulo'          => 'Mediunidade Servico XZ Unico',
            'status'          => Post::STATUS_PUBLICADO,
            'visualizacoes'   => 0,
            'data_publicacao' => now()->subDays(6),
        ]);

        Livewire::test(Lista::class)
            ->set('q', 'Raciocinada')
            ->assertSee('Fe Raciocinada XZ Unico')
            ->assertDontSee('Mediunidade Servico XZ Unico');
    }
}
