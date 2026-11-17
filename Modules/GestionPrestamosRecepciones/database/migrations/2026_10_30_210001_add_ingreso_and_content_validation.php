<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->string('documento_ingreso_ruta')->nullable();
            $table->string('documento_ingreso_sha256', 64)->nullable();
            $table->timestampTz('recepcion_qr_en')->nullable();
            $table->uuid('recepcion_qr_por')->nullable();
            $table->timestampTz('recepcion_qr_rechazada_en')->nullable();
            $table->text('recepcion_qr_motivo')->nullable();
            $table->timestampTz('contenido_validado_en')->nullable();
            $table->uuid('contenido_validado_por')->nullable();
        });
        Schema::create('recepciones.contenido_recepcion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('solicitud_id');
            $table->foreign('solicitud_id')->references('id')->on('recepciones.solicitudes_deposito');
            $table->string('identificador');
            $table->unsignedInteger('cantidad');
            $table->jsonb('datos_dwc');
            $table->text('observaciones')->nullable();
            $table->uuid('validado_por');
            $table->timestampTz('validado_en');
            $table->timestampsTz();
            $table->unique(['solicitud_id', 'identificador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.contenido_recepcion');
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->dropColumn(['documento_ingreso_ruta', 'documento_ingreso_sha256', 'recepcion_qr_en', 'recepcion_qr_por', 'recepcion_qr_rechazada_en', 'recepcion_qr_motivo', 'contenido_validado_en', 'contenido_validado_por']);
        });
    }
};
