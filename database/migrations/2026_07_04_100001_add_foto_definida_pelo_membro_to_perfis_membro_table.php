<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->boolean('foto_definida_pelo_membro')->default(false)->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('perfis_membro', function (Blueprint $table) {
            $table->dropColumn('foto_definida_pelo_membro');
        });
    }
};
