<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Support\Mensagens\GlossarioCamposMensagem;
use Tests\TestCase;

/**
 * Trava a paridade entre as DUAS listas mantidas à mão: o `logOnly` do model e a lista branca
 * do glossário. Campo em `logOnly` e fora do glossário some do histórico do DEPAE em SILÊNCIO
 * (HistoricoMensagem descarta chave sem rótulo) — este teste é a única rede contra esse drift.
 */
class GlossarioCamposParidadeTest extends TestCase
{
    public function test_glossario_cobre_exatamente_os_campos_do_log_only(): void
    {
        $logOnly = (new Mensagem)->getActivitylogOptions()->logAttributes;
        $glossario = array_keys(GlossarioCamposMensagem::CAMPOS_ROTULOS);

        sort($logOnly);
        sort($glossario);

        $this->assertSame($logOnly, $glossario, 'logOnly e o glossário divergiram');
    }

    public function test_resumo_esta_nas_duas_listas(): void
    {
        $this->assertContains('resumo', (new Mensagem)->getActivitylogOptions()->logAttributes);
        $this->assertArrayHasKey('resumo', GlossarioCamposMensagem::CAMPOS_ROTULOS);
    }
}
