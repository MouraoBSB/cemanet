<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('origem_legado_id')->nullable()->unique()->after('id');
            $table->boolean('socio')->default(false)->index()->after('email');
            $table->boolean('ativo')->default(true)->after('socio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['origem_legado_id', 'socio', 'ativo']);
        });
    }
};
