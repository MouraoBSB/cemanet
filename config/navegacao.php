<?php

// Navegação principal do site (header e footer). Itens com 'ativo' => false
// ficam desabilitados (placeholder) até o módulo correspondente existir.
return [
    'menu' => [
        [
            'rotulo' => 'Institucional',
            'ativo' => false,
            'itens' => [
                ['rotulo' => 'Nossa História', 'ativo' => false],
                ['rotulo' => 'Contato', 'ativo' => false],
            ],
        ],
        [
            'rotulo' => 'Palestras',
            'rota' => 'palestras.index',
            'ativo' => true,
            'itens' => [
                ['rotulo' => 'Palestras Públicas', 'rota' => 'palestras.index', 'ativo' => true],
                ['rotulo' => 'Palestrantes', 'ativo' => false],
            ],
        ],
        ['rotulo' => 'Mensagens Mediúnicas', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Eventos', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Vibração', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Agenda', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Evangelho', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Sementeira', 'ativo' => false, 'itens' => []],
    ],
];
