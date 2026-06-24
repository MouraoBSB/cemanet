<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('palestras', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->string('subtitulo')->nullable();
            $table->text('resumo')->nullable();
            $table->longText('descricao')->nullable();
            $table->dateTime('data_da_palestra');
            $table->boolean('online')->default(false);
            $table->string('link_youtube')->nullable();
            $table->string('cor_fundo')->nullable();
            $table->integer('publico_online')->nullable();
            $table->integer('publico_presencial')->nullable();
            $table->integer('publico_total')->nullable();
            $table->string('status')->default('publicado');
            $table->timestamps();

            $table->index('data_da_palestra');
            $table->index('status');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('palestras');
    }
};
