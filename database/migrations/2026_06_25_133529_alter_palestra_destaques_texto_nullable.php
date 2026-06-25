<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Torna `texto` opcional em destaques (campo de conteúdo complementar). */
    public function up(): void
    {
        Schema::table('palestra_destaques', function (Blueprint $table) {
            $table->text('texto')->nullable()->change();
        });
    }

    /** Reverte: `texto` volta a ser NOT NULL (converte NULL → string vazia). */
    public function down(): void
    {
        // Converte NULLs existentes antes de remover nullable
        DB::table('palestra_destaques')->whereNull('texto')->update(['texto' => '']);

        Schema::table('palestra_destaques', function (Blueprint $table) {
            $table->text('texto')->nullable(false)->change();
        });
    }
};
