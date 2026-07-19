<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_mensagem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['mensagem_id', 'departamento_id'], 'departamento_mensagem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_mensagem');
    }
};
