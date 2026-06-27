<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\TipTap;

use Tiptap\Core\Extension;

/**
 * Espelho PHP (candidato B) da extensão JS imagemAlinhada.
 * Estende Extension (não o ImageExtension do Filament) e injeta os atributos
 * `align` e `size` no nó `image` existente via addGlobalAttributes().
 * Isso evita duplicar o nó image no schema — abordagem robusta e sem dependência
 * de prioridade/ordem de inserção.
 */
class ImagemAtributosExtension extends Extension
{
    public static $name = 'imagemAtributos';

    private const CLASSES_ALIGN = [
        'left'   => 'alignleft',
        'right'  => 'alignright',
        'center' => 'aligncenter',
        'none'   => 'alignnone',
    ];

    private const CLASSES_SIZE = [
        'medium' => 'size-medium',
        'large'  => 'size-large',
        'full'   => 'size-full',
    ];

    /**
     * Injeta os atributos `align` e `size` no nó `image` nativo do TipTap.
     * HTML::mergeAttributes garante que dois atributos retornando { class: '...' }
     * terão suas classes concatenadas — ambas aparecem no mesmo atributo class.
     *
     * @return array<array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types'      => ['image'],
                'attributes' => [
                    'align' => [
                        'default'    => null,
                        'parseHTML'  => function ($DOMNode) {
                            $classes = array_filter(
                                explode(' ', (string) $DOMNode->getAttribute('class'))
                            );

                            foreach (self::CLASSES_ALIGN as $k => $c) {
                                if (in_array($c, $classes, true)) {
                                    return $k;
                                }
                            }

                            return null;
                        },
                        'renderHTML' => fn ($attributes) => isset($attributes->align, self::CLASSES_ALIGN[$attributes->align])
                            ? ['class' => self::CLASSES_ALIGN[$attributes->align]]
                            : [],
                    ],
                    'size' => [
                        'default'    => null,
                        'parseHTML'  => function ($DOMNode) {
                            $classes = array_filter(
                                explode(' ', (string) $DOMNode->getAttribute('class'))
                            );

                            foreach (self::CLASSES_SIZE as $k => $c) {
                                if (in_array($c, $classes, true)) {
                                    return $k;
                                }
                            }

                            return null;
                        },
                        'renderHTML' => fn ($attributes) => isset($attributes->size, self::CLASSES_SIZE[$attributes->size])
                            ? ['class' => self::CLASSES_SIZE[$attributes->size]]
                            : [],
                    ],
                ],
            ],
        ];
    }
}
