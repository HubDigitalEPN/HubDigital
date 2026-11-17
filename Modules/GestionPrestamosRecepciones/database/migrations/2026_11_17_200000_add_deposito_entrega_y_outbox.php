<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $t): void {
            // NULL conserva la procedencia de expedientes históricos, sin inventar declaraciones.
            $t->string('finalidad_actividad', 30)->nullable();
            $t->jsonb('historial_entregas')->default('[]');
        });
        Schema::create('recepciones.ingreso_pendientes', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('solicitud_id')->unique();
            $t->foreign('solicitud_id')->references('id')->on('recepciones.solicitudes_deposito')->cascadeOnDelete();
            $t->jsonb('snapshot');
            $t->string('estado', 40)->default('pendiente')->index();
            $t->string('correo_estado', 40)->default('pendiente');
            $t->unsignedSmallInteger('intentos')->default(0);
            $t->uuid('lease')->nullable();
            $t->timestampTz('ocupado_hasta')->nullable();
            $t->timestampTz('reintentar_en')->nullable();
            $t->timestampTz('despachado_en')->nullable();
            $t->timestampTz('campana_encolada_en')->nullable();
            $t->timestampTz('correo_aceptado_en')->nullable();
            $t->string('ultimo_error', 80)->nullable();
            $t->jsonb('historial_reintentos')->default('[]');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.ingreso_pendientes');
        Schema::table('recepciones.solicitudes_deposito', fn (Blueprint $t) => $t->dropColumn(['finalidad_actividad', 'historial_entregas']));
    }
};
