<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivô das mensagens DIRECIONADAS (N:N mensagem↔usuário). É PII: só o resolvedor o consome.
        Schema::create('mensagem_destinatario', function (Blueprint $table) {
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['mensagem_id', 'user_id'], 'mensagem_destinatario_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_destinatario');
    }
};
