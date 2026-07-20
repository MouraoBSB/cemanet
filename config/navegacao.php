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
                ['rotulo' => 'Palestrantes', 'rota' => 'palestrantes.index', 'ativo' => true],
            ],
        ],
        [
            'rotulo' => 'Mensagens Mediúnicas',
            'rota' => 'mensagens.index',
            'ativo' => true,
            'itens' => [
                ['rotulo' => 'Mensagens Públicas', 'rota' => 'mensagens.index', 'ativo' => true],
                ['rotulo' => 'Autores Espirituais', 'rota' => 'autores.index', 'ativo' => true],
            ],
        ],
        ['rotulo' => 'Eventos', 'rota' => 'eventos.index', 'ativo' => true, 'itens' => []],
        ['rotulo' => 'Vibração', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Agenda', 'rota' => 'agenda.index', 'ativo' => true, 'itens' => []],
        ['rotulo' => 'Evangelho', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Sementeira', 'rota' => 'blog.index', 'ativo' => true, 'itens' => []],
    ],
];
