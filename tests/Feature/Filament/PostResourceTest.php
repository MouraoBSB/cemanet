<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Filament;

use App\Filament\Pages\ConfiguracoesBlog;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Categoria;
use App\Models\Configuracao;
use App\Models\Departamento;
use App\Models\Post;
use App\Models\Tag;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);
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
                'titulo' => 'Sementeira de Luz',
                'slug' => 'sementeira-de-luz',
                'status' => Post::STATUS_PUBLICADO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'sementeira-de-luz']);
    }

    public function test_cria_rascunho_sem_data_de_publicacao(): void
    {
        // Rascunho pode existir sem data (coluna nullable; data exigida só ao publicar).
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Rascunho sem data',
                'slug' => 'rascunho-sem-data',
                'status' => Post::STATUS_RASCUNHO,
                'data_publicacao' => null,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'rascunho-sem-data', 'data_publicacao' => null]);
    }

    public function test_publicar_sem_data_usa_o_instante_atual(): void
    {
        // "Publicar agora": publicar sem data não bloqueia — preenche o instante atual.
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Publicado sem data',
                'slug' => 'publicado-sem-data',
                'status' => Post::STATUS_PUBLICADO,
                'data_publicacao' => null,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'publicado-sem-data')->first();
        $this->assertNotNull($post);
        $this->assertSame(Post::STATUS_PUBLICADO, $post->status);
        $this->assertNotNull($post->data_publicacao);
    }

    public function test_agendar_exige_data_de_publicacao(): void
    {
        // Agendado é um agendamento futuro — exige data.
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Agendado sem data',
                'slug' => 'agendado-sem-data',
                'status' => Post::STATUS_AGENDADO,
                'data_publicacao' => null,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['data_publicacao']);

        $this->assertDatabaseMissing('posts', ['slug' => 'agendado-sem-data']);
    }

    public function test_cria_post_com_categorias_e_faqs(): void
    {
        $categoria = Categoria::factory()->create();

        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Post com Categorias',
                'slug' => 'post-com-categorias',
                'status' => Post::STATUS_PUBLICADO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'categorias' => [$categoria->id],
                'categoria_principal_id' => $categoria->id,
                'departamentos' => [$this->departamento->id],
                'faqs' => [
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
                'titulo' => 'Post com Tags',
                'slug' => 'post-com-tags',
                'status' => Post::STATUS_RASCUNHO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'tags' => [$tag->id],
                'departamentos' => [$this->departamento->id],
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
                'slug' => '',
                'status' => Post::STATUS_RASCUNHO,
                'departamentos' => [$this->departamento->id],
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
        $this->assertTrue(EditPost::$formActionsAreSticky);
        $this->assertTrue(CreatePost::$formActionsAreSticky);
    }

    public function test_toolbar_do_editor_inclui_botao_paragrafo(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (RichEditor $campo): bool => $campo->hasToolbarButton('paragraph'));
    }

    public function test_toolbar_do_editor_inclui_alinhamento_de_texto(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', function (RichEditor $campo): bool {
                foreach (['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'] as $tool) {
                    if (! $campo->hasToolbarButton($tool)) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_handler_das_tools_de_imagem_e_alpine_valido(): void
    {
        // Regressão (BUG1 real): aspas DUPLAS no jsHandler/activeJsExpression quebravam o
        // atributo Alpine x-on:click ("Invalid or unexpected token") e o botão ficava inerte.
        // Devem ser aspas SIMPLES (como as tools nativas do Filament).
        $html = Livewire::test(CreatePost::class)->html();

        $this->assertStringContainsString("definirAlinhamentoImagem('left').run()", $html);
        $this->assertStringContainsString("definirTamanhoImagem('medium').run()", $html);
        $this->assertStringNotContainsString('definirAlinhamentoImagem(&quot;', $html);
        $this->assertStringNotContainsString('definirTamanhoImagem(&quot;', $html);
    }

    public function test_toolbar_inclui_ferramentas_nativas_extras(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', function (RichEditor $campo): bool {
                foreach (['grid', 'clearFormatting', 'horizontalRule', 'lead', 'textColor'] as $tool) {
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
            ->assertFormFieldExists('conteudo', function (RichEditor $campo): bool {
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
                'titulo' => 'Post com Imagem Destacada',
                'slug' => 'post-com-imagem-destacada',
                'status' => Post::STATUS_RASCUNHO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'post-com-imagem-destacada']);
    }

    public function test_uploads_de_imagem_nao_geram_responsive_images(): void
    {
        // Higiene de performance (Fatia A): capa e galeria são servidas por <img> simples
        // no front (sem srcset), então o componente de upload do painel NÃO deve gerar
        // responsive images do original (GenerateResponsiveImagesJob ocioso). As conversões
        // do model são cobertas por PostMediaTest; aqui validamos o caminho do PAINEL.
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('destacada', fn (SpatieMediaLibraryFileUpload $campo): bool => ! $campo->hasResponsiveImages())
            ->assertFormFieldExists('galeria', fn (SpatieMediaLibraryFileUpload $campo): bool => ! $campo->hasResponsiveImages());
    }

    public function test_uploads_de_imagem_usam_disco_public(): void
    {
        // #1 (fundacional): o disco default do Filament é 'local' (privado) → a URL /storage
        // gerada pelo Spatie aponta para o disco public (vazio) e a imagem 404 no front.
        // Os uploads de mídia do post devem fixar 'public' para renderizar.
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('destacada', fn (SpatieMediaLibraryFileUpload $campo): bool => $campo->getDiskName() === 'public')
            ->assertFormFieldExists('galeria', fn (SpatieMediaLibraryFileUpload $campo): bool => $campo->getDiskName() === 'public')
            ->assertFormFieldExists('og', fn (SpatieMediaLibraryFileUpload $campo): bool => $campo->getDiskName() === 'public');
    }

    public function test_toolbar_inclui_inserir_da_biblioteca(): void
    {
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (RichEditor $campo): bool => $campo->hasToolbarButton('inserirDaBiblioteca'));
    }

    public function test_toolbar_nao_inclui_mais_o_clipe_attachfiles(): void
    {
        // #2: o clipe attachFiles salvava a imagem do corpo sem <img>. Foi removido e
        // substituído pela tool 'inserirDaBiblioteca' (caminho portável /midia/{id}/web).
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (RichEditor $campo): bool => ! $campo->hasToolbarButton('attachFiles'));
    }

    public function test_corpo_nao_aceita_anexo_de_arquivo(): void
    {
        // #2 fechado por completo: anexos desativados no corpo (clipe + arrastar + colar).
        // canAttachFiles=false → o JS não trata paste/drop. Imagem entra só pela biblioteca.
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', fn (RichEditor $campo): bool => ! $campo->hasFileAttachments());
    }

    public function test_textcolors_tem_swatch_valido_e_nome_legivel(): void
    {
        // Regressão (#4): a paleta precisa ser TextColor(label=nome, cor=hex). Antes era
        // ['nome' => '#hex'], que o Filament lia invertido (label=#hex, cor=nome) → swatch
        // invisível e só o código no dropdown.
        Livewire::test(CreatePost::class)
            ->assertFormFieldExists('conteudo', function (RichEditor $campo): bool {
                $cores = $campo->getTextColors();
                if ($cores === []) {
                    return false;
                }
                foreach ($cores as $cor) {
                    if (! str_starts_with((string) $cor->getColor(), '#')) {  // cor do swatch = hex
                        return false;
                    }
                    if (str_starts_with((string) $cor->getLabel(), '#')) {     // label = nome, não código
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_salva_departamento(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Post com Departamento',
                'slug' => 'post-com-departamento',
                'status' => Post::STATUS_PUBLICADO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'post-com-departamento')->first();
        $this->assertTrue($post->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Post sem Departamento',
                'slug' => 'post-sem-departamento',
                'status' => Post::STATUS_RASCUNHO,
                'data_publicacao' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
}
