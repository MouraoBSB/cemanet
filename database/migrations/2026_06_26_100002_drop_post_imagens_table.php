<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Remove a tabela post_imagens (substituída pela Media Library). */
    public function up(): void
    {
        Schema::dropIfExists('post_imagens');
    }

    /** Recria a tabela post_imagens (reversão). */
    public function down(): void
    {
        Schema::create('post_imagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('caminho');
            $table->string('url_legado')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }
};
