<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setor_usuario', function (Blueprint $table) {
            $table->foreignId('setor_id')->constrained('setores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('funcao', ['membro', 'coordenador'])->default('membro');
            $table->date('desde')->nullable();
            $table->timestamps();
            $table->primary(['setor_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setor_usuario');
    }
};
