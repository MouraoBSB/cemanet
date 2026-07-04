<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_membro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('whatsapp')->nullable();
            $table->boolean('whatsapp_publico')->default(false);
            $table->date('data_nascimento')->nullable();
            $table->text('endereco')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis_membro');
    }
};
