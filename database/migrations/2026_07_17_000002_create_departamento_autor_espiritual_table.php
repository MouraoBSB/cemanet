<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_autor_espiritual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autor_espiritual_id')->constrained('autores_espirituais')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            // Nome do índice EXPLÍCITO: o automático do Laravel seria
            // 'departamento_autor_espiritual_autor_espiritual_id_departamento_id_unique' = 72 caracteres,
            // e o MySQL limita identificadores a 64 (erro 1059) — mesmo caso já resolvido em
            // 2026_07_16_000002_create_departamento_tipo_conteudo_table.php. O SQLite dos testes não
            // tem esse limite, então a suíte não pega: só o migrate no MySQL pega.
            $table->unique(['autor_espiritual_id', 'departamento_id'], 'departamento_autor_espiritual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_autor_espiritual');
    }
};
