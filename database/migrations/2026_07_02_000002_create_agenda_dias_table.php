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
        Schema::create('agenda_dias', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();               // chave natural / idempotência
            $table->text('reflexao')->nullable();          // HTML (Evangelho, ref. embutida)
            $table->text('meta_mes_texto')->nullable();    // HTML (citação diária)
            $table->string('meta_dia_titulo')->nullable(); // dura vários dias
            $table->text('meta_dia_texto')->nullable();    // HTML
            $table->text('prece')->nullable();             // HTML
            $table->string('status')->default('publicado');
            $table->unsignedBigInteger('wp_id')->nullable()->unique(); // rastreio do legado
            $table->timestamps();
            $table->index('status');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_dias');
    }
};
