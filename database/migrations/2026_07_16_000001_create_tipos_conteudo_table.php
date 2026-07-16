<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_conteudo', function (Blueprint $table) {
            $table->id();
            $table->string('recurso')->unique();   // slug de GlossarioCapacidades::RECURSOS
            $table->string('regime');              // App\Enums\RegimeAcesso
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_conteudo');
    }
};
