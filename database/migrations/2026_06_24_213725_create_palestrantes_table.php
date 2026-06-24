<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migration.
     */
    public function up(): void
    {
        Schema::create('palestrantes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('foto')->nullable();
            $table->longText('bio')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->boolean('mostrar_email')->default(false);
            $table->boolean('mostrar_telefone')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverte a migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('palestrantes');
    }
};
