<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorBlog;
use App\Importacao\LeitorBlog;
use App\Importacao\ReescritorImagensConteudo;
use App\Models\Post;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorBlogTest extends TestCase
{
    use RefreshDatabase;

    /** Gera bytes de uma imagem JPEG real decodável pelo GD/getimagesize. */
    private function imagemBytes(string $nome = 'img.jpg', int $w = 800, int $h = 600): string
    {
        return UploadedFile::fake()->image($nome, $w, $h)->getContent();
    }

    private function leitorFake(
        string $urlConteudo = '',
        string $urlGaleria = 'https://example.com/galeria1.jpg',
    ): LeitorBlog {
        $conteudo = $urlConteudo
            ? "<p>Texto.</p><img src=\"{$urlConteudo}\" alt=\"img\">"
            : '<p>Texto longo o suficiente para calculo.</p>';

        return new class ($conteudo, $urlGaleria) implements LeitorBlog
        {
            public function __construct(
                private string $conteudo,
                private string $urlGaleria,
            ) {}

            public function posts(): array
            {
                return [[
                    'titulo'                   => 'Luz e Amor',
                    'slug'                     => 'luz-e-amor',
                    'resumo'                   => 'Um belo post',
                    'conteudo'                 => $this->conteudo,
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
                        ['url' => $this->urlGaleria, 'wp_id' => 456, 'ordem' => 0],
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

        // Bytes reais: o GD consegue ler mime e dimensões
        $bytes = $this->imagemBytes('foto.jpg', 800, 600);
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, ['leitor' => $this->leitorFake()]);

        // Roda 2× para garantir idempotência
        $r1 = $importador->importar();
        $r2 = $importador->importar();

        $this->assertSame(1, $r1['posts']);
        $this->assertSame(1, $r2['posts']);

        // Não duplica posts
        $this->assertSame(1, Post::count());

        $post = Post::first();
        $this->assertSame('publicado', $post->status);

        // Só a categoria conhecida foi vinculada
        $this->assertCount(1, $post->categorias);
        $this->assertSame('reflexoes-e-espiritualidade', $post->categoriaPrincipal->slug);

        // Tags criadas e vinculadas
        $this->assertCount(2, $post->tags);

        // FAQs (delete + recreate, sem duplicar)
        $this->assertCount(1, $post->faqs);

        // Media Library: imagem destacada
        $this->assertCount(1, $post->getMedia(Post::COLECAO_DESTACADA));

        // Media Library: og
        $this->assertCount(1, $post->getMedia(Post::COLECAO_OG));

        // Media Library: galeria
        $this->assertCount(1, $post->getMedia(Post::COLECAO_GALERIA));

        // Dados básicos
        $this->assertSame(123, $post->wp_id);
        $this->assertNull($post->criado_por_id);

        // Aviso sobre categoria desconhecida: exato e não-acumulado entre rodadas
        $this->assertSame(
            ['[luz-e-amor] categoria desconhecida: categoria-inexistente'],
            $r1['avisos'],
        );
        $this->assertSame(
            ['[luz-e-amor] categoria desconhecida: categoria-inexistente'],
            $r2['avisos'],
        );
    }

    public function test_grava_imagem_do_corpo_na_ml(): void
    {
        Storage::fake('public');

        $urlImg = 'https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg';
        $bytes = $this->imagemBytes('foto.jpg', 800, 600);
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, [
            'leitor' => $this->leitorFake(urlConteudo: $urlImg),
        ]);

        $importador->importar();

        $post = Post::first();
        $this->assertCount(1, $post->getMedia(Post::COLECAO_CONTEUDO));
    }

    public function test_idempotencia_nao_duplica_imagens_ml(): void
    {
        Storage::fake('public');

        $urlImg = 'https://cemanet.org.br/wp-content/uploads/2025/01/foto.jpg';
        $bytes = $this->imagemBytes('foto.jpg', 800, 600);
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, [
            'leitor' => $this->leitorFake(urlConteudo: $urlImg),
        ]);

        $importador->importar();
        $importador->importar();

        $post = Post::first();

        // Reimport não deve empilhar cópias
        $this->assertCount(1, $post->getMedia(Post::COLECAO_DESTACADA));
        $this->assertCount(1, $post->getMedia(Post::COLECAO_OG));
        $this->assertCount(1, $post->getMedia(Post::COLECAO_GALERIA));
        $this->assertCount(1, $post->getMedia(Post::COLECAO_CONTEUDO));
    }

    public function test_cap_imagem_destacada_respeita_teto_2000px(): void
    {
        Storage::fake('public');

        // Imagem acima do teto: 2400×800
        $bytes = $this->imagemBytes('big.jpg', 2400, 800);
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, ['leitor' => $this->leitorFake()]);
        $importador->importar();

        $post = Post::first();
        $media = $post->getFirstMedia(Post::COLECAO_DESTACADA);
        $this->assertNotNull($media);

        $dim = @getimagesize($media->getPath());
        $this->assertNotFalse($dim, 'Arquivo de mídia não é uma imagem válida');
        $this->assertLessThanOrEqual(2000, max($dim[0], $dim[1]));
    }

    public function test_cap_imagem_og_respeita_teto_1200px(): void
    {
        Storage::fake('public');

        // Imagem OG acima do teto: 1600×900
        $bytes = $this->imagemBytes('og-grande.jpg', 1600, 900);
        Http::fake(['*' => Http::response($bytes, 200)]);

        $this->seed(CategoriaSeeder::class);

        $importador = app(ImportadorBlog::class, ['leitor' => $this->leitorFake()]);
        $importador->importar();

        $post = Post::first();
        $media = $post->getFirstMedia(Post::COLECAO_OG);
        $this->assertNotNull($media);

        $dim = @getimagesize($media->getPath());
        $this->assertNotFalse($dim, 'Arquivo de mídia OG não é uma imagem válida');
        $this->assertLessThanOrEqual(1200, max($dim[0], $dim[1]));
    }

    public function test_galeria_preserva_a_ordem_das_imagens(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->imagemBytes(), 200)]);
        $this->seed(CategoriaSeeder::class);

        // Leitor com galeria de 2 itens em ordem; assertamos que a coleção ML
        // preserva essa ordem (via custom property url_legado).
        $leitor = new class implements LeitorBlog
        {
            public function posts(): array
            {
                return [[
                    'titulo'                   => 'Galeria Ordenada',
                    'slug'                     => 'galeria-ordenada',
                    'resumo'                   => null,
                    'conteudo'                 => '<p>Texto longo o suficiente para calculo.</p>',
                    'data_publicacao'          => Carbon::parse('2026-01-15'),
                    'status'                   => 'publicado',
                    'wp_id'                    => 999,
                    'imagem_url'               => null,
                    'imagem_alt'               => null,
                    'categorias_slugs'         => [],
                    'categoria_principal_slug' => null,
                    'tags'                     => [],
                    'faqs'                     => [],
                    'galeria'                  => [
                        ['url' => 'https://example.com/primeira.jpg', 'wp_id' => 1, 'ordem' => 0],
                        ['url' => 'https://example.com/segunda.jpg', 'wp_id' => 2, 'ordem' => 1],
                    ],
                    'seo' => [],
                ]];
            }
        };

        app(ImportadorBlog::class, ['leitor' => $leitor])->importar();

        $galeria = Post::first()->getMedia(Post::COLECAO_GALERIA);
        $this->assertCount(2, $galeria);
        $this->assertSame('https://example.com/primeira.jpg', $galeria[0]->getCustomProperty('url_legado'));
        $this->assertSame('https://example.com/segunda.jpg', $galeria[1]->getCustomProperty('url_legado'));
    }

    public function test_baixar_capado_ignora_valor_nao_url_sem_lancar(): void
    {
        // Regressão: o og_imagem do legado vinha como array PHP serializado ("a:2:{…}").
        // Passar isso ao Http::get fazia o Guzzle lançar ("scheme a"), derrubando o import.
        Http::fake();

        $r = app(\App\Importacao\BaixadorImagem::class)->baixarCapado('a:2:{s:5:"check";b:1;}', 1200);

        $this->assertNull($r);
        Http::assertNothingSent(); // nem tentou baixar — rejeitado antes do request
    }
}
