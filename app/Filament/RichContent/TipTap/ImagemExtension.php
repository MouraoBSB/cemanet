<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\TipTap;

use Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension;

/**
 * Espelho PHP (candidato A) da extensão JS imagemAlinhada.
 * Estende o ImageExtension do Filament e adiciona o atributo `align`
 * que faz parse/render via classes WP (alignleft/alignright/etc.).
 */
class ImagemExtension extends ImageExtension
{
    private const CLASSES = [
        'left'   => 'alignleft',
        'right'  => 'alignright',
        'center' => 'aligncenter',
        'none'   => 'alignnone',
    ];

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            'align' => [
                'default'    => null,
                'parseHTML'  => function ($DOMNode) {
                    $classes = array_filter(
                        explode(' ', (string) $DOMNode->getAttribute('class'))
                    );

                    foreach (self::CLASSES as $k => $c) {
                        if (in_array($c, $classes, true)) {
                            return $k;
                        }
                    }

                    return null;
                },
                'renderHTML' => fn ($attributes) => isset($attributes->align, self::CLASSES[$attributes->align])
                    ? ['class' => self::CLASSES[$attributes->align]]
                    : [],
            ],
        ];
    }
}
