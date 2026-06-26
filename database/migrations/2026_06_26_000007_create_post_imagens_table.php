<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
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

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('post_imagens');
    }
};
