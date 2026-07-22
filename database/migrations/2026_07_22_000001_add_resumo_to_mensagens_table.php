<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            // Texto editorial do legado (post_excerpt, ≤1164 chars) — texto PURO, sem HTML.
            $table->text('resumo')->nullable()->after('contexto');
        });
    }

    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->dropColumn('resumo');
        });
    }
};
