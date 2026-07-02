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
        Schema::create('agenda_metas_mes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('titulo');
            $table->timestamps();
            $table->unique(['ano', 'mes']);
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_metas_mes');
    }
};
