<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

use App\Models\Post;
use Illuminate\Support\Facades\Log;

class ReescritorImagensConteudo
{
    public function __construct(
        private readonly BaixadorImagem $baixador,
    ) {}

    public function reescrever(string $html, string $slugPost, Post $post): string
    {
        // Idempotência: limpa imagens anteriores do corpo antes de reprocessar
        $post->clearMediaCollection(Post::COLECAO_CONTEUDO);

        $regex = '~<img([^>]+)src=["\']([^"\']+wp-content/uploads/[^"\']+)["\']~i';

        return preg_replace_callback($regex, function (array $m) use ($slugPost, $post): string {
            $atributos = $m[1];
            $url = $m[2];
            $tagOriginal = $m[0];

            $bytes = $this->baixador->baixarCapado($url, 2000);

            if ($bytes === null) {
                Log::warning('ReescritorImagensConteudo: falha ao baixar imagem', [
                    'url'       => $url,
                    'slug_post' => $slugPost,
                ]);

                return $tagOriginal;
            }

            $nomeArquivo = basename(parse_url($url, PHP_URL_PATH) ?? 'img.jpg');

            $media = $post->addMediaFromString($bytes)
                ->usingFileName($nomeArquivo)
                ->withCustomProperties(['url_legado' => $url])
                ->toMediaCollection(Post::COLECAO_CONTEUDO);

            // Usa o caminho RELATIVO (/storage/...) — independente de host/porta. A URL
            // fica "assada" no HTML salvo; relativa, ela resolve contra a origem da página
            // (localhost:8000 no dev, domínio em produção) sem quebrar se o APP_URL mudar.
            $novaUrl = parse_url($media->getUrl('web'), PHP_URL_PATH) ?: $media->getUrl('web');

            // Substitui a URL legada pela URL da Media Library
            $tagReescrita = str_replace($url, $novaUrl, $tagOriginal);

            // Injeta o data-id com o UUID da mídia — é o que o provider do RichEditor
            // compara no cleanup de órfãos (whereIn('uuid', ...)). Usar o id numérico
            // (getKey) faria o save de um post migrado no admin APAGAR as imagens do corpo.
            if (! str_contains($atributos, 'data-id')) {
                $tagReescrita = str_replace('<img', '<img data-id="' . $media->uuid . '"', $tagReescrita);
            }

            return $tagReescrita;
        }, $html) ?? $html;
    }
}
