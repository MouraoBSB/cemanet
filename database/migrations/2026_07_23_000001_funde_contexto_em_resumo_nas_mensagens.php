<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Funde `contexto` em `resumo` (F4c-D). O texto só é copiado onde `resumo` está vazio (NULL ou '')
 * e `contexto` tem conteúdo; onde os dois estão preenchidos o `resumo` VENCE, e o `contexto` é
 * descartado junto com a coluna, na migration seguinte.
 *
 * DB::table e não Eloquent: o model tem LogsActivity, e um laço com ->save() viraria uma enxurrada
 * de "mensagem atualizada" no histórico que o diretor do DEPAE lê — mesmo motivo do
 * activity()->withoutLogs() em ImportarResumosMensagens.php:45.
 *
 * Dev em 2026-07-22: 181 mensagens, 2 com contexto, 1 realmente copiada (id 191).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('mensagens')
            ->whereNotNull('contexto')
            ->where('contexto', '<>', '')
            ->where(function ($consulta) {
                // blank() em SQL: NULL e '' — mesmo critério de ImportarResumosMensagens.php:68.
                $consulta->whereNull('resumo')->orWhere('resumo', '');
            })
            // Identificador NU de propósito: "contexto" vira literal de string no MySQL e
            // `contexto` quebra no SQLite. Sem aspas, os dois drivers leem a coluna.
            ->update(['resumo' => DB::raw('contexto')]);
    }

    public function down(): void
    {
        // Sem reversão: nada distingue o resumo que veio do contexto do que sempre foi resumo.
        // Molde de 2026_06_29_000001_mover_fotos_palestrante_para_media_library.php:33.
    }
};
