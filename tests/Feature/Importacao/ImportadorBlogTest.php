<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorBlog;
use App\Importacao\LeitorBlog;
use App\Importacao\ReescritorImagensConteudo;
use App\Models\Post;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorBlogTest extends TestCase
{
    use RefreshDatabase;

    private function leitorFake(): LeitorBlog
    {
        return new class implements LeitorBlog
        {
            public function posts(): array
            {
                return [[
                    'titulo'                   => 'Luz e Amor',
                    'slug'                     => 'luz-e-amor',
                    'resumo'                   => 'Um belo post',
                    'conteudo'                 => '<p>Texto longo o suficiente para calculo.</p>',
                    'data_publicacao'          => Carbon::parse('2026-01-15'),
                    'status'                   => 'publicado',
                    'wp_id'                    => 123,
                    'imagem_url'               => 'https://example.com/foto.jpg',
                    'imagem_alt'               => 'Foto de luz',
                    'categorias_slugs'         => ['reflexoes-e-espiritualidade', 'categoria-inexistente'],
                    'categoria_principal_slug' => 'reflexoes-e-espiritualidade',
                    'tags'                     => [
                        ['nome' => 'Amor', 'slug' => 'amor'],
                        ['nome' => 'Luz', 'slug' => 'luz'],
                    ],
                    'faqs' => [
                        ['pergunta' => 'O que é amor?', 'resposta' => 'É a base de tudo.', 'ordem' => 0],
                    ],
                    'galeria' => [
                        ['url' => 'https://example.com/galeria1.jpg', 'wp_id' => 456, 'ordem' => 0],
                    ],
                    'seo' => [
                        'titulo'    => 'SEO Luz',
                        'descricao' => 'desc seo',
                        'keyword'   => 'luz amor',
                        'og_imagem' => 'https://example.com/og.jpg',
                    ],
                ]];
            }
        };
    }

    public function test_importa_e_e_idempotente(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('img_bytes', 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, ['leitor' => $this->leitorFake()]);

        // roda 2x para garantir idempotência
        $importador->importar();
        $resumo = $importador->importar();

        // não duplica
        $this->assertSame(1, Post::count());

        $post = Post::first();
        $this->assertSame('publicado', $post->status);

        // só a categoria conhecida foi vinculada
        $this->assertCount(1, $post->categorias);
        $this->assertSame('reflexoes-e-espiritualidade', $post->categoriaPrincipal->slug);

        // tags criadas e vinculadas
        $this->assertCount(2, $post->tags);

        // FAQs (delete + recreate, mas sem duplicar)
        $this->assertCount(1, $post->faqs);

        // imagem da galeria
        $this->assertCount(1, $post->imagens);

        // dados básicos
        $this->assertSame(123, $post->wp_id);
        $this->assertNull($post->criado_por_id);

        // aviso sobre categoria desconhecida
        $this->assertNotEmpty($resumo['avisos']);
    }
}
