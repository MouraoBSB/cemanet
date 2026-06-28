<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `data_publicacao` passa a ser NULLABLE: um RASCUNHO pode existir sem data definida
 * (a data é exigida só ao publicar/agendar, via validação no formulário). Antes, criar
 * um rascunho sem data quebrava com violação de NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dateTime('data_publicacao')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dateTime('data_publicacao')->nullable(false)->change();
        });
    }
};
