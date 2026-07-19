<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();   // idempotência do legado
            $table->string('titulo');
            $table->string('slug')->unique();                            // 39 pending sem slug → gerado no import
            $table->longText('corpo')->nullable();
            $table->text('contexto')->nullable();                        // OA: faixa editorial manual (não IA); nasce null
            $table->string('formato')->nullable();                       // enum FormatoMensagem
            $table->date('data_recebimento')->nullable();                // dia-granular; nullable de origem
            $table->string('casa')->default('CEMA');
            $table->string('link_arquivo', 500)->nullable();             // M-A: alinha com o maxLength(500) do form
            $table->boolean('liberar_download')->default(false);
            $table->string('nivel')->nullable();                         // BRUTO (slug da taxonomia); 49/179 null
            $table->string('status')->default('publicado');              // publicado | pendente | despublicada
            $table->timestamps();

            $table->index('status');
            $table->index('data_recebimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
