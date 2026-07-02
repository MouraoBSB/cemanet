<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('agenda_slugs_legado', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // post_name real (numérico OU de data)
            $table->date('data');             // destino do 301 (N slugs → 1 data)
            $table->index('data');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_slugs_legado');
    }
};
