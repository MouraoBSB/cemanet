<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('assuntos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('assuntos')->nullOnDelete();
            $table->timestamps();
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('assuntos');
    }
};
