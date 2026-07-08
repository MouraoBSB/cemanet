<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumo')->nullable();
            $table->longText('conteudo')->nullable();
            $table->date('data_inicio');
            $table->string('hora_inicio', 5)->nullable();
            $table->date('data_fim')->nullable();
            $table->string('hora_fim', 5)->nullable();
            $table->string('local')->nullable();
            $table->foreignId('categoria_evento_id')->nullable()->constrained('categorias_evento')->nullOnDelete();
            $table->string('visibilidade')->default('publico');
            $table->string('status')->default('publicado');
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->timestamps();

            $table->index('data_inicio');
            $table->index('data_fim');
            $table->index('status');
            $table->index('visibilidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
