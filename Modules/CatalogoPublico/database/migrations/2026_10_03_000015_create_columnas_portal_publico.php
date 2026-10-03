<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomia.columnas_portal_publico', function (Blueprint $table): void {
            $table->string('clave', 100)->primary();
            $table->boolean('visible')->default(true);
            $table->uuid('actualizado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.columnas_portal_publico');
    }
};
