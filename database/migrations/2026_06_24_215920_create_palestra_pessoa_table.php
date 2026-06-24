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
        Schema::create('palestra_pessoa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('palestrantes')->cascadeOnDelete();
            $table->enum('papel', ['palestrante', 'diretor']);
            $table->timestamps();

            $table->unique(['palestra_id', 'pessoa_id', 'papel']);
        });
    }

    /**
     * Reverte a migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('palestra_pessoa');
    }
};
