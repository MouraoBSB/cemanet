<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumo')->nullable();
            $table->longText('conteudo')->nullable();
            $table->string('imagem_destacada')->nullable();
            $table->string('imagem_destacada_alt')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('categoria_principal_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->boolean('destaque')->default(false);
            $table->unsignedSmallInteger('tempo_leitura_min')->default(0);
            $table->unsignedInteger('visualizacoes')->default(0);
            $table->dateTime('data_publicacao');
            $table->string('status')->default('publicado');
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->string('seo_titulo')->nullable();
            $table->string('seo_descricao')->nullable();
            $table->string('seo_keyword')->nullable();
            $table->string('og_imagem')->nullable();
            $table->boolean('robots_noindex')->default(false);
            $table->string('canonical')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('data_publicacao');
            $table->index('destaque');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
