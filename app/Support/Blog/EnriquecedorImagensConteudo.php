<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Support\Blog;

use App\Models\Biblioteca;
use DOMDocument;
use DOMElement;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EnriquecedorImagensConteudo
{
    /**
     * @return array{html: string, imagens: array<int, array<string, mixed>>}
     */
    public function enriquecer(?string $html): array
    {
        if (blank($html)) {
            return ['html' => (string) $html, 'imagens' => []];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // meta charset evita mangle de UTF-8; NOIMPLIED/NODEFDTD evita <html><body>.
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="cema-raiz">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $raiz = $dom->getElementById('cema-raiz');
        if (! $raiz) {
            return ['html' => $html, 'imagens' => []];
        }

        // 1) coleta ids das imagens da biblioteca presentes
        $imgs = [];   // [ [DOMElement, id], ... ]
        foreach ($dom->getElementsByTagName('img') as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            if (preg_match('#^/midia/(\d+)/#', $src, $m)) {
                $imgs[] = [$img, (int) $m[1]];
            }
        }

        if ($imgs === []) {
            return ['html' => $html, 'imagens' => []];
        }

        // 2) busca as mídias em UMA query
        $ids = array_values(array_unique(array_map(fn ($x) => $x[1], $imgs)));
        $midias = Media::query()
            ->where('collection_name', Biblioteca::COLECAO)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        // 3) envolve em figure/figcaption + coleta ImageObject
        $imagens = [];
        foreach ($imgs as [$img, $id]) {
            $midia = $midias->get($id);

            $legenda   = $midia?->getCustomProperty('legenda');
            $titulo    = $midia?->getCustomProperty('titulo');
            $descricao = $midia?->getCustomProperty('descricao');

            // <figure>…<img>…[<figcaption>legenda</figcaption>]</figure>
            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', 'figura-conteudo');
            $img->parentNode?->replaceChild($figure, $img);
            $figure->appendChild($img);
            if (filled($legenda)) {
                $cap = $dom->createElement('figcaption');
                $cap->appendChild($dom->createTextNode($legenda)); // createTextNode ESCAPA (anti-XSS)
                $figure->appendChild($cap);
            }

            // ImageObject (omitindo chaves vazias)
            $obj = array_filter([
                '@type'       => 'ImageObject',
                'contentUrl'  => url('/midia/' . $id . '/web'),
                'caption'     => $legenda,
                'name'        => $titulo,
                'description' => $descricao,
            ], fn ($v) => filled($v));
            $imagens[] = $obj;
        }

        // 4) extrai o innerHTML do wrapper
        $out = '';
        foreach (iterator_to_array($raiz->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return ['html' => $out, 'imagens' => $imagens];
    }
}
