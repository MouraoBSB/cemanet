<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Remove colunas de imagem antigas migradas para a Media Library. */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['imagem_destacada', 'og_imagem']);
        });
    }

    /** Recria as colunas removidas (reversão). */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('imagem_destacada')->nullable();
            $table->string('og_imagem')->nullable();
        });
    }
};
