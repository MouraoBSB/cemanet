<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atributo_usuario', function (Blueprint $table) {
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('desde')->nullable();
            $table->date('ate')->nullable();
            $table->timestamps();
            $table->primary(['atributo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atributo_usuario');
    }
};
