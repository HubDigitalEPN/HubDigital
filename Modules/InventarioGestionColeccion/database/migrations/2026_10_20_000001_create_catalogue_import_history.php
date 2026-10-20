<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void { $table->text('notes_general')->nullable(); });
        Schema::create('taxonomia.importaciones_catalogo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('archivo_nombre', 255);
            $table->string('fuente_sha256', 64);
            $table->string('modo', 16)->default('completar');
            $table->unique(['fuente_sha256', 'modo']);
            $table->string('archivo_ruta', 500);
            $table->string('extension', 8);
            $table->string('delimitador', 1)->default(',');
            $table->uuid('creado_por_id');
            $table->string('estado', 24)->default('en_cola');
            $table->string('plan_sha256', 64);
            $table->jsonb('resumen')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
        Schema::create('taxonomia.importacion_filas_catalogo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('importacion_id');
            $table->foreign('importacion_id')->references('id')->on('taxonomia.importaciones_catalogo');
            $table->unsignedInteger('fila');
            $table->uuid('especimen_id')->nullable();
            $table->string('codigo', 120);
            $table->string('accion', 24);
            $table->jsonb('original');
            $table->jsonb('anterior')->nullable();
            $table->jsonb('posterior')->nullable();
            $table->unique(['importacion_id', 'fila']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.importacion_filas_catalogo');
        Schema::dropIfExists('taxonomia.importaciones_catalogo');
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void { $table->dropColumn('notes_general'); });
    }
};
