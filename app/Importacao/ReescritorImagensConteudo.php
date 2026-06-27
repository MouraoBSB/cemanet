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
        // Idempotência: limpa imagens migradas anteriores antes de reprocessar.
        // Coleção `corpo` (NÃO `conteudo`): o cleanup de órfãos do RichEditor só atua
        // na `conteudo`, então as migradas — <img> simples, sem data-id — nunca são
        // apagadas quando o admin abre e salva um post.
        $post->clearMediaCollection(Post::COLECAO_CORPO);

        $regex = '~<img[^>]+src=["\']([^"\']+wp-content/uploads/[^"\']+)["\']~i';

        return preg_replace_callback($regex, function (array $m) use ($slugPost, $post): string {
            $url = $m[1];
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
                ->toMediaCollection(Post::COLECAO_CORPO);

            // Caminho RELATIVO (/storage/...) da conversão web — independente de host/porta
            // e "assado" no HTML como <img> simples (o editor preserva o src e não o apaga).
            $novaUrl = parse_url($media->getUrl('web'), PHP_URL_PATH) ?: $media->getUrl('web');

            return str_replace($url, $novaUrl, $tagOriginal);
        }, $html) ?? $html;
    }
}
