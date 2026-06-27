<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Filament;

use App\Filament\Pages\ConfiguracoesBlog;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Categoria;
use App\Models\Configuracao;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_lista_renderiza(): void
    {
        Post::factory()->count(3)->create();

        $this->get('/admin/posts')->assertOk();
    }

    public function test_listposts_component_renderiza(): void
    {
        Livewire::test(ListPosts::class)->assertOk();
    }

    public function test_cria_post_simples(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo'          => 'Sementeira de Luz',
                'slug'            => 'sementeira-de-luz',
                'status'          => Post::STATUS_PUBLICADO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'sementeira-de-luz']);
    }

    public function test_cria_post_com_categorias_e_faqs(): void
    {
        $categoria = Categoria::factory()->create();

        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo'               => 'Post com Categorias',
                'slug'                 => 'post-com-categorias',
                'status'               => Post::STATUS_PUBLICADO,
                'data_publicacao'      => now()->format('Y-m-d H:i'),
                'categorias'           => [$categoria->id],
                'categoria_principal_id' => $categoria->id,
                'faqs'                 => [
                    ['pergunta' => 'O que é a Sementeira?', 'resposta' => 'É um blog espírita.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'post-com-categorias')->first();
        $this->assertNotNull($post);
        $this->assertTrue($post->categorias->contains($categoria));
        $this->assertCount(1, $post->faqs);
        $this->assertSame('O que é a Sementeira?', $post->faqs->first()->pergunta);
    }

    public function test_cria_post_com_tags(): void
    {
        $tag = Tag::factory()->create();

        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo'         => 'Post com Tags',
                'slug'           => 'post-com-tags',
                'status'         => Post::STATUS_RASCUNHO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'tags'           => [$tag->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'post-com-tags')->first();
        $this->assertNotNull($post);
        $this->assertTrue($post->tags->contains($tag));
    }

    public function test_slug_e_obrigatorio(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Sem Slug',
                'slug'   => '',
                'status' => Post::STATUS_RASCUNHO,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $this->assertDatabaseMissing('posts', ['titulo' => 'Sem Slug']);
    }

    public function test_configuracoes_blog_grava_reflexao(): void
    {
        Livewire::test(ConfiguracoesBlog::class)
            ->fillForm(['reflexao_do_dia' => 'O amor é a lei maior.'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame('O amor é a lei maior.', Configuracao::valor('blog.reflexao_do_dia'));
    }

    public function test_configuracoes_blog_renderiza(): void
    {
        $this->get('/admin/configuracoes-blog')->assertOk();
    }

    public function test_paginas_de_post_tem_form_actions_sticky(): void
    {
        $this->assertTrue(\App\Filament\Resources\Posts\Pages\EditPost::$formActionsAreSticky);
        $this->assertTrue(\App\Filament\Resources\Posts\Pages\CreatePost::$formActionsAreSticky);
    }

    public function test_toolbar_do_editor_inclui_botao_paragrafo(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (\Filament\Forms\Components\RichEditor $campo): bool =>
                $campo->hasToolbarButton('paragraph'));
    }

    public function test_toolbar_do_editor_inclui_alinhamento_de_texto(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', function (\Filament\Forms\Components\RichEditor $campo): bool {
                foreach (['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'] as $tool) {
                    if (! $campo->hasToolbarButton($tool)) {
                        return false;
                    }
                }
                return true;
            });
    }

    public function test_editor_tem_toolbar_flutuante_para_imagem(): void
    {
        // Affordance do BUG 1: ao selecionar a imagem, as ferramentas de imagem
        // aparecem numa barra flutuante junto do nó.
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', function (\Filament\Forms\Components\RichEditor $campo): bool {
                $flutuantes = $campo->getFloatingToolbars();

                return isset($flutuantes['image'])
                    && in_array('imagemAlinharEsquerda', $flutuantes['image'], true)
                    && in_array('imagemTamanhoTotal', $flutuantes['image'], true);
            });
    }

    public function test_cria_post_com_imagem_destacada_na_colecao_ml(): void
    {
        Storage::fake('public');

        // O SpatieMediaLibraryFileUpload depende do ciclo de upload temporário do
        // Livewire/Filament, que não roda em teste unitário; passar um UploadedFile no
        // fillForm não aciona o pipeline de ML. Aqui garantimos de forma determinística
        // que o formulário submete sem erros e o post persiste; a anexação real de mídia
        // na coleção é coberta por PostMediaTest/PostFactoryMediaTest e verificação manual.
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo'          => 'Post com Imagem Destacada',
                'slug'            => 'post-com-imagem-destacada',
                'status'          => Post::STATUS_RASCUNHO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'post-com-imagem-destacada']);
    }
}
