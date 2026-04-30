<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_prestamo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('numero_solicitud', 10)->unique();
            $table->string('investigador_id');
            $table->string('estado')->default('Borrador');
            $table->string('titulo_estudio')->nullable();
            $table->string('institucion_adscripcion')->nullable();
            $table->string('linea_investigacion')->nullable();
            $table->text('proposito_prestamo')->nullable();
            $table->unsignedInteger('duracion_propuesta_meses')->nullable();
            $table->text('justificacion_extendida')->nullable();
            $table->text('comentario_curador')->nullable();
            $table->timestamp('enviada_en')->nullable();
            $table->timestamp('resuelta_en')->nullable();
            $table->string('resuelta_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_prestamo');
    }
};
