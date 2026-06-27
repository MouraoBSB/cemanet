<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class BaixadorImagem
{
    public function baixar(?string $url, string $slug): ?string
    {
        return $this->baixarPara($url, 'palestrantes', $slug);
    }

    public function baixarPara(?string $url, string $pasta, string $nome): ?string
    {
        if (empty($url)) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
        $caminho = "{$pasta}/{$nome}.{$ext}";
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

    /**
     * Baixa a URL e retorna os bytes em memória, capando o lado mais longo a $teto px.
     * Retorna null em URL vazia, falha HTTP ou exceção.
     */
    public function baixarCapado(?string $url, int $teto = 2000): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Blindagem: só baixa URLs http(s) reais. O legado às vezes traz lixo no lugar
        // da URL (ex.: o og_imagem do Rank Math vem como array PHP serializado "a:2:{…}"),
        // e passar isso ao Http::get faz o Guzzle interpretar o esquema como "a" e lançar,
        // derrubando a importação inteira. Aqui vira aviso e segue.
        if (! preg_match('~^https?://~i', $url)) {
            \Illuminate\Support\Facades\Log::warning('BaixadorImagem: URL inválida ignorada', [
                'url' => mb_strimwidth($url, 0, 80, '…'),
            ]);

            return null;
        }

        try {
            $resposta = Http::timeout(30)->get($url);
            if (! $resposta->successful()) {
                return null;
            }
            $bytes = $resposta->body();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        // Detecta extensão a partir do mime-type para que o Spatie Image consiga abrir
        $tmpSemExt = tempnam(sys_get_temp_dir(), 'cema_mid');
        file_put_contents($tmpSemExt, $bytes);
        $mime = mime_content_type($tmpSemExt) ?: 'image/jpeg';
        @unlink($tmpSemExt);

        $ext = match ($mime) {
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cema_mid_' . uniqid() . '.' . $ext;
        file_put_contents($tmp, $bytes);

        // try/finally garante a limpeza do temp mesmo se o GD falhar ao redimensionar
        // uma imagem corrompida; nesse caso devolve null (vira aviso no importador) em
        // vez de propagar a exceção e derrubar a transação do post inteiro.
        try {
            $dim = @getimagesize($tmp);
            if ($dim !== false && max($dim[0], $dim[1]) > $teto) {
                Image::load($tmp)->fit(Fit::Max, $teto, $teto)->save();
            }

            return file_get_contents($tmp);
        } catch (\Throwable $e) {
            report($e);

            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
