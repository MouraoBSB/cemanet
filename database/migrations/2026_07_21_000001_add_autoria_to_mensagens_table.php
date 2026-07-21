<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            // constrained('users') EXPLÍCITO: sem o nome da tabela, Str::plural('medium') === 'media'
            // e a FK apontaria em SILÊNCIO para a biblioteca de mídia (§3.1/C-F).
            $table->foreignId('medium_id')->nullable()->after('casa')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por_id')->nullable()->after('medium_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_em')->nullable()->after('publicado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            // dropConstrainedForeignId: dropForeign('nome_string') lança RuntimeException no SQLite dos testes.
            $table->dropConstrainedForeignId('medium_id');
            $table->dropConstrainedForeignId('publicado_por_id');
            $table->dropColumn('publicado_em');
        });
    }
};
