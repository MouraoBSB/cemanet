<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Mensagens;

/**
 * Fonte única do vocabulário de CAMPOS de Mensagem no histórico de auditoria (Fatia F4b, Task 11).
 * Lista branca: só os campos aqui aparecem no histórico — qualquer chave fora dela é IGNORADA
 * (nunca um fallback com o nome cru da coluna). Mesmo molde de
 * App\Support\Autorizacao\GlossarioCapacidades. Mesmos 11 campos de
 * Mensagem::getActivitylogOptions()->logOnly([...]) — paridade travada por
 * Tests\Feature\Mensagens\GlossarioCamposParidadeTest.
 */
class GlossarioCamposMensagem
{
    public const CAMPOS_ROTULOS = [
        'titulo' => 'Título',
        'slug' => 'Slug',
        'corpo' => 'Corpo da mensagem',
        'resumo' => 'Resumo',
        'formato' => 'Formato',
        'data_recebimento' => 'Data de recebimento',
        'casa' => 'Casa',
        'link_arquivo' => 'Link do arquivo',
        'liberar_download' => 'Liberar download',
        'nivel' => 'Nível de acesso',
        'status' => 'Status',
    ];

    /** Rótulo pt-BR do campo, ou null se fora da lista branca — o chamador deve IGNORAR, nunca exibir o nome cru. */
    public static function rotulo(string $campo): ?string
    {
        return self::CAMPOS_ROTULOS[$campo] ?? null;
    }
}
