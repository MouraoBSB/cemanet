<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_autor_espiritual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('autor_espiritual_id')->constrained('autores_espirituais')->cascadeOnDelete();

            // Nome EXPLÍCITO: o auto do Laravel daria exatos 64 chars (margem zero p/ o limite do MySQL).
            $table->unique(['mensagem_id', 'autor_espiritual_id'], 'mensagem_autor_espiritual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_autor_espiritual');
    }
};
