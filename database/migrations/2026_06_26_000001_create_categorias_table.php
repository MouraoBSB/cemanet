<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('cor', 7)->nullable();
            $table->string('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unsignedBigInteger('wp_term_id')->nullable();
            $table->timestamps();
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
