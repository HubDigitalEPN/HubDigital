<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomia.eliminaciones_especimenes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Identidad histórica, sin FK ni copia recuperable del espécimen.
            $table->uuid('especimen_id')->index();
            $table->string('codigo_catalogo', 100);
            $table->uuid('eliminado_por')->nullable();
            $table->timestampTz('eliminado_en');
        });
    }

    public function down(): void { Schema::dropIfExists('taxonomia.eliminaciones_especimenes'); }
};
