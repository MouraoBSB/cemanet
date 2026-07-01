<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Support\Blog;

class PlacarSeo
{
    /**
     * Analisa o conteúdo e retorna uma nota SEO de 0 a 100 com checklist de itens.
     *
     * @return array{nota: int, itens: list<array{ok: bool, rotulo: string}>}
     */
    public static function analisar(
        ?string $conteudo,
        ?string $titulo,
        ?string $keyword,
        ?string $descricao,
    ): array {
        $conteudo = $conteudo ?? '';
        $titulo = $titulo ?? '';
        $keyword = $keyword ?? '';
        $descricao = $descricao ?? '';

        // Texto limpo (sem tags HTML) para análise de palavras e parágrafo inicial
        $textoLimpo = strip_tags($conteudo);

        // Sinal 1: keyword definida (não vazia)
        $keywordDefinida = $keyword !== '';

        // Sinal 2: keyword no título (case-insensitive, suporte a acentos via mb_stripos)
        $keywordNoTitulo = $keywordDefinida && mb_stripos($titulo, $keyword) !== false;

        // Sinal 3: keyword no 1º parágrafo (~120 primeiros caracteres do texto sem tags)
        $primeiros120 = mb_substr($textoLimpo, 0, 120);
        $keywordNoParagrafo = $keywordDefinida && mb_stripos($primeiros120, $keyword) !== false;

        // Sinal 4: densidade da keyword entre 0,5% e 2,5%
        // Usa regex Unicode (\pL) para contar palavras corretamente com acentos
        $densidadeOk = false;
        if ($keywordDefinida && $textoLimpo !== '') {
            $totalPalavras = preg_match_all('/\pL+/u', $textoLimpo);
            if ($totalPalavras > 0) {
                // Conta ocorrências da keyword (case-insensitive, Unicode)
                $ocorrencias = preg_match_all(
                    '/'.preg_quote($keyword, '/').'/iu',
                    $textoLimpo,
                );
                // Palavras da keyword via regex Unicode (suporte a acentos)
                $palavrasKeyword = preg_match_all('/\pL+/u', $keyword);
                $densidade = ($ocorrencias * $palavrasKeyword) / $totalPalavras * 100;
                $densidadeOk = $densidade >= 0.5 && $densidade <= 2.5;
            }
        }

        // Sinal 5: conteúdo com pelo menos 300 palavras (regex Unicode)
        $palavrasSuficientes = preg_match_all('/\pL+/u', $textoLimpo) >= 300;

        // Sinal 6: há subtítulo <h2> ou <h3>
        $temSubtitulo = (bool) preg_match('/<h[23][^>]*>/i', $conteudo);

        // Sinal 7: todas as <img> têm atributo alt não vazio
        $imgsComAlt = true;
        $totalImgs = preg_match_all('/<img\b[^>]*>/i', $conteudo, $matchesImg);
        if ($totalImgs > 0) {
            foreach ($matchesImg[0] as $tag) {
                // Verifica se a tag possui alt="algo não vazio"
                if (! preg_match('/\balt\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|(\S+))/i', $tag)) {
                    $imgsComAlt = false;
                    break;
                }
            }
        }

        // Sinal 8: meta description preenchida e com 50–160 caracteres
        $lenDesc = mb_strlen($descricao);
        $descricaoOk = $descricao !== '' && $lenDesc >= 50 && $lenDesc <= 160;

        // Sinal 9: há ao menos um link <a href
        $temLink = (bool) preg_match('/<a\s[^>]*href/i', $conteudo);

        $itens = [
            ['ok' => $keywordDefinida,    'rotulo' => 'Keyword definida'],
            ['ok' => $keywordNoTitulo,    'rotulo' => 'Keyword no título'],
            ['ok' => $keywordNoParagrafo, 'rotulo' => 'Keyword no 1º parágrafo'],
            ['ok' => $densidadeOk,        'rotulo' => 'Densidade da keyword (0,5%–2,5%)'],
            ['ok' => $palavrasSuficientes, 'rotulo' => 'Conteúdo com ≥ 300 palavras'],
            ['ok' => $temSubtitulo,       'rotulo' => 'Subtítulo (h2 ou h3)'],
            ['ok' => $imgsComAlt,         'rotulo' => 'Imagens com atributo alt'],
            ['ok' => $descricaoOk,        'rotulo' => 'Meta description (50–160 caracteres)'],
            ['ok' => $temLink,            'rotulo' => 'Há ao menos um link'],
        ];

        $aprovados = count(array_filter($itens, fn ($i) => $i['ok']));
        $nota = (int) round($aprovados / count($itens) * 100);

        return ['nota' => $nota, 'itens' => $itens];
    }
}
