<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->string('chamada')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->dropColumn('chamada');
        });
    }
};
