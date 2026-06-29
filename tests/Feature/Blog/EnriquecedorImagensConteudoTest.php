<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace Tests\Feature\Blog;

use App\Models\Biblioteca;
use App\Support\Blog\EnriquecedorImagensConteudo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class EnriquecedorImagensConteudoTest extends TestCase
{
    use RefreshDatabase;

    private EnriquecedorImagensConteudo $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->servico = app(EnriquecedorImagensConteudo::class);
    }

    /** Cria uma mídia na biblioteca e retorna o Model Media. */
    private function criarMidia(array $props = []): Media
    {
        $lib = Biblioteca::instance();

        $conteudoImagem = UploadedFile::fake()->image('foto.jpg', 100, 100)->getContent();

        $midia = $lib
            ->addMediaFromString($conteudoImagem)
            ->usingFileName('foto.jpg')
            ->toMediaCollection(Biblioteca::COLECAO);

        foreach ($props as $chave => $valor) {
            $midia->setCustomProperty($chave, $valor);
        }

        if (! empty($props)) {
            $midia->save();
        }

        return $midia->fresh();
    }

    /** 1) img /midia/{id}/web com legenda → figure + figcaption + ImageObject com caption. */
    public function test_imagem_com_legenda_vira_figure_com_figcaption_e_gera_image_object(): void
    {
        $midia = $this->criarMidia(['legenda' => 'Minha legenda']);

        $html = '<p><img src="/midia/' . $midia->id . '/web" alt="foto"></p>';
        $out  = $this->servico->enriquecer($html);

        $this->assertStringContainsString('<figure', $out['html']);
        $this->assertStringContainsString('<figcaption>Minha legenda</figcaption>', $out['html']);
        $this->assertCount(1, $out['imagens']);
        $this->assertSame('ImageObject', $out['imagens'][0]['@type']);
        $this->assertArrayHasKey('caption', $out['imagens'][0]);
        $this->assertSame('Minha legenda', $out['imagens'][0]['caption']);
        $this->assertStringContainsString('/midia/' . $midia->id . '/web', $out['imagens'][0]['contentUrl']);
    }

    /** 2) img /midia/{id}/web SEM legenda → NÃO vira figure (img simples), mas gera ImageObject. */
    public function test_imagem_sem_legenda_nao_vira_figure_mas_gera_image_object(): void
    {
        $midia = $this->criarMidia([]);

        $html = '<img src="/midia/' . $midia->id . '/web" alt="sem legenda">';
        $out  = $this->servico->enriquecer($html);

        $this->assertStringNotContainsString('<figure', $out['html']);
        $this->assertStringContainsString('/midia/' . $midia->id . '/web', $out['html']);
        $this->assertCount(1, $out['imagens']);
        $this->assertSame('ImageObject', $out['imagens'][0]['@type']);
        $this->assertStringContainsString('/midia/' . $midia->id . '/web', $out['imagens'][0]['contentUrl']);
        $this->assertArrayNotHasKey('caption', $out['imagens'][0]);
    }

    /** 6) imagem COM legenda e classe de alinhamento/tamanho → a classe migra da img para o figure. */
    public function test_classe_de_alinhamento_migra_para_o_figure(): void
    {
        $midia = $this->criarMidia(['legenda' => 'Cap']);

        $html = '<img src="/midia/' . $midia->id . '/web" class="aligncenter size-large" alt="x">';
        $out  = $this->servico->enriquecer($html);

        // a classe de alinhamento/tamanho vai para o <figure>
        $this->assertMatchesRegularExpression('/<figure class="figura-conteudo[^"]*aligncenter[^"]*"/', $out['html']);
        $this->assertMatchesRegularExpression('/<figure class="figura-conteudo[^"]*size-large[^"]*"/', $out['html']);
        // e sai da <img>
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*\baligncenter\b/', $out['html']);
    }

    /** 3) img /storage/... (migrada) fica intacta, sem figure e sem ImageObject. */
    public function test_imagem_storage_fica_intacta_sem_figure_e_sem_image_object(): void
    {
        $html = '<img src="/storage/posts/foto-migrada.jpg" alt="migrada">';
        $out  = $this->servico->enriquecer($html);

        $this->assertStringNotContainsString('<figure', $out['html']);
        $this->assertStringContainsString('/storage/posts/foto-migrada.jpg', $out['html']);
        $this->assertCount(0, $out['imagens']);
    }

    /** 4) conteúdo nulo → retorna como veio, imagens=[]. */
    public function test_conteudo_nulo_retorna_string_vazia_e_imagens_vazias(): void
    {
        $out = $this->servico->enriquecer(null);

        $this->assertSame('', $out['html']);
        $this->assertSame([], $out['imagens']);
    }

    /** 4b) conteúdo vazio → retorna como veio, imagens=[]. */
    public function test_conteudo_vazio_retorna_como_veio_e_imagens_vazias(): void
    {
        $out = $this->servico->enriquecer('');

        $this->assertSame('', $out['html']);
        $this->assertSame([], $out['imagens']);
    }

    /** 5) legenda com caracteres especiais é escapada no figcaption (não vira HTML). */
    public function test_legenda_com_html_e_escapada_no_figcaption(): void
    {
        $midia = $this->criarMidia(['legenda' => '<b>texto</b> & "aspas"']);

        $html = '<img src="/midia/' . $midia->id . '/web" alt="escape">';
        $out  = $this->servico->enriquecer($html);

        // Deve aparecer escapado, não como HTML renderizado
        $this->assertStringContainsString('&lt;b&gt;texto&lt;/b&gt;', $out['html']);
        $this->assertStringNotContainsString('<b>texto</b>', $out['html']);
        $this->assertStringContainsString('&amp;', $out['html']);
    }
}
