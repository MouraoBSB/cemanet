<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->string('slide')->nullable()->after('link_youtube');
            $table->string('duracao', 40)->nullable()->after('slide');
            $table->text('referencias_evangelicas')->nullable()->after('descricao');
            $table->unsignedInteger('curtidas')->default(0)->after('publico_total');
        });
    }

    public function down(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->dropColumn(['slide', 'duracao', 'referencias_evangelicas', 'curtidas']);
        });
    }
};
