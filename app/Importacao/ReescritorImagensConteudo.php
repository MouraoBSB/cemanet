<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReescritorImagensConteudo
{
    public function __construct(
        private readonly BaixadorImagem $baixador,
    ) {}

    public function reescrever(string $html, string $slugPost): string
    {
        $regex = '~<img[^>]+src=["\']([^"\']+wp-content/uploads/[^"\']+)["\']~i';

        return preg_replace_callback($regex, function (array $m) use ($slugPost, &$html): string {
            $url = $m[1];
            $caminho = $this->baixador->baixarPara($url, 'blog/conteudo', md5($url));

            if ($caminho === null) {
                Log::warning('ReescritorImagensConteudo: falha ao baixar imagem', [
                    'url' => $url,
                    'slug_post' => $slugPost,
                ]);

                return $m[0];
            }

            return str_replace($url, Storage::url($caminho), $m[0]);
        }, $html) ?? $html;
    }
}
