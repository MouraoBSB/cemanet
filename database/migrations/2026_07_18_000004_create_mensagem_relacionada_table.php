<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_relacionada', function (Blueprint $table) {
            $table->id();
            // Auto-referente: AMBAS as FKs apontam p/ 'mensagens' — o nome da tabela é OBRIGATÓRIO
            // (o Laravel inferiria a tabela pelo nome da coluna e erraria em 'relacionada_id').
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('relacionada_id')->constrained('mensagens')->cascadeOnDelete();

            $table->unique(['mensagem_id', 'relacionada_id'], 'mensagem_relacionada_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_relacionada');
    }
};
