<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('divulgacion.imagenes_taxonomicas')) {
            return;
        }

        Schema::create('divulgacion.imagenes_taxonomicas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('occurrence_id');
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('disco')->default('public');
            $table->string('autor_nombre');
            $table->string('autor_apellido');
            $table->string('autor_nombre_completo');
            $table->boolean('marca_agua_aplicada')->default(false);
            $table->timestamps();

            // Referencia lógica a taxonomia.especimenes.occurrence_id (no es único allí,
            // por lo que no se modela como FK). La integridad la garantiza el handler.
            $table->index('occurrence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divulgacion.imagenes_taxonomicas');
    }
};
