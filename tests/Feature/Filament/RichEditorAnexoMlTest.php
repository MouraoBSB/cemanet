<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Tests\Feature\Filament;

use App\Models\Post;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RichEditorAnexoMlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function coleção_conteudo_esta_registrada_no_post(): void
    {
        $post = new Post;

        $atributo = $post->getRichContentAttribute(Post::COLECAO_CONTEUDO);

        $this->assertNotNull(
            $atributo,
            'RichContentAttribute para a coleção "conteudo" deve estar registrado no Post.'
        );
    }

    #[Test]
    public function provider_da_colecao_conteudo_e_spatie_media_library(): void
    {
        $post = new Post;

        $provider = $post
            ->getRichContentAttribute(Post::COLECAO_CONTEUDO)
            ?->getFileAttachmentProvider();

        $this->assertInstanceOf(
            SpatieMediaLibraryFileAttachmentProvider::class,
            $provider,
            'O provider de anexos do RichEditor deve ser o SpatieMediaLibraryFileAttachmentProvider.'
        );
    }

    #[Test]
    public function colecao_conteudo_esta_definida_nas_colecoes_de_midia(): void
    {
        $post = new Post;

        // Forçar o carregamento das coleções
        $post->registerMediaCollections();

        $nomes = collect($post->mediaCollections)
            ->map(fn ($c) => $c->name)
            ->toArray();

        $this->assertContains(
            Post::COLECAO_CONTEUDO,
            $nomes,
            'A coleção "conteudo" deve estar registrada em registerMediaCollections().'
        );
    }
}
