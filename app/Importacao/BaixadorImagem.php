<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BaixadorImagem
{
    public function baixar(?string $url, string $slug): ?string
    {
        if (empty($url)) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
        $caminho = "palestrantes/{$slug}.{$ext}";
        $disco = Storage::disk('public');

        if ($disco->exists($caminho)) {
            return $caminho;
        }

        try {
            $resposta = Http::timeout(30)->get($url);
            if (! $resposta->successful()) {
                return null;
            }
            $disco->put($caminho, $resposta->body());

            return $caminho;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
