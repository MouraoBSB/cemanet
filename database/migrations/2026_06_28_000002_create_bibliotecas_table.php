<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliotecas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->unique()->default('principal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliotecas');
    }
};
