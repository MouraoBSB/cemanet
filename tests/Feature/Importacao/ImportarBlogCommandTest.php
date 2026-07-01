<?php

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorBlog;
use App\Models\Post;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarBlogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_usando_o_leitor_injetado(): void
    {
        Storage::fake('public');

        // Bytes reais para que o importador processe sem erros de mime/dimensões
        $bytes = UploadedFile::fake()->image('img.jpg', 800, 600)->getContent();
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        // Injeta um leitor fake no container (evita depender do legado)
        $this->app->bind(LeitorBlog::class, fn () => new class implements LeitorBlog
        {
            public function posts(): array
            {
                return [[
                    'wp_id' => 1,
                    'titulo' => 'Post de teste',
                    'slug' => 'post-de-teste',
                    'resumo' => null,
                    'conteudo' => '<p>Conteúdo simples.</p>',
                    'data_publicacao' => Carbon::parse('2026-01-15 10:00:00'),
                    'status' => 'publicado',
                    'imagem_url' => null,
                    'imagem_alt' => null,
                    'categorias_slugs' => ['reflexoes-e-espiritualidade'],
                    'categoria_principal_slug' => 'reflexoes-e-espiritualidade',
                    'tags' => [],
                    'faqs' => [],
                    'galeria' => [],
                    'seo' => ['titulo' => null, 'descricao' => null, 'keyword' => null, 'og_imagem' => null],
                ]];
            }
        });

        $this->artisan('cema:importar-blog')
            ->expectsOutputToContain('Importação concluída')
            ->assertExitCode(0);

        $this->assertSame(1, Post::count());
    }
}
