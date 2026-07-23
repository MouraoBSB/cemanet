<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

use App\Models\Mensagem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O rótulo dizia "Imagens (pictografia)" mas o front só renderizava no formato Pictografia —
 * imagem em psicografia sumia sem aviso. Ao corrigir isso, o nome técnico da coleção também
 * deixa de mentir. Os arquivos NÃO se movem: o path da medialibrary usa o id da media, e as
 * conversões (web/thumb) já geradas continuam válidas. 4 linhas no dev.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')
            ->where('model_type', Mensagem::class)   // FQCN literal: não há morph map no projeto
            ->where('collection_name', 'pictografia')
            ->update(['collection_name' => 'imagens']);
    }

    public function down(): void
    {
        DB::table('media')
            ->where('model_type', Mensagem::class)
            ->where('collection_name', 'imagens')
            ->update(['collection_name' => 'pictografia']);
    }
};
