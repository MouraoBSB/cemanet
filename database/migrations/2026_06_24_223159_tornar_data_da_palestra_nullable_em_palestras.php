<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migration.
     *
     * Nem toda palestra publicada no legado tem data definida (ex.: "Paz e Nós").
     * Permitir null preserva essas palestras em vez de descartá-las ou inventar data.
     */
    public function up(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->dateTime('data_da_palestra')->nullable()->change();
        });
    }

    /**
     * Reverte a migration.
     */
    public function down(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->dateTime('data_da_palestra')->nullable(false)->change();
        });
    }
};
