<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove a coluna `foto`: a foto do palestrante passou a viver na Media Library
     * (coleção `foto`). As fotos já foram copiadas para a ML pela migration anterior.
     */
    public function up(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }

    public function down(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->string('foto')->nullable();
        });
    }
};
