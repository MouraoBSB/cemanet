<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('palestra_destaques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->string('destaque');
            $table->text('texto');
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('palestra_destaques');
    }
};
