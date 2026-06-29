<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('palestra_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->string('obra');
            $table->string('autor')->nullable();
            $table->text('nota')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('palestra_referencias');
    }
};
