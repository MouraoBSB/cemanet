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
        Schema::create('assunto_palestra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->foreignId('assunto_id')->constrained('assuntos')->cascadeOnDelete();
            $table->unique(['palestra_id', 'assunto_id']);
        });
    }

    /**
     * Reverte a migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('assunto_palestra');
    }
};
