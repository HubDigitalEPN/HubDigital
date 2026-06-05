<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina columnas que dejaron de usarse en el dominio:
 * - iot.cajas.capacidad_maxima            (la caja ya no modela capacidad)
 * - iot.ranuras_gabinete.familia_taxonomica_esperada_id
 *   (el orden taxonómico esperado proviene de la caja que ocupa la ranura, no de la ranura)
 *
 * Migración aditiva: las tablas originales se crearon en 2026_05_01; editarlas in-place
 * dejaría drift entre la BD viva y un entorno nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot.cajas', function (Blueprint $table): void {
            if (Schema::hasColumn('iot.cajas', 'capacidad_maxima')) {
                $table->dropColumn('capacidad_maxima');
            }
        });

        Schema::table('iot.ranuras_gabinete', function (Blueprint $table): void {
            if (Schema::hasColumn('iot.ranuras_gabinete', 'familia_taxonomica_esperada_id')) {
                $table->dropColumn('familia_taxonomica_esperada_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('iot.cajas', function (Blueprint $table): void {
            if (! Schema::hasColumn('iot.cajas', 'capacidad_maxima')) {
                $table->unsignedSmallInteger('capacidad_maxima')->nullable()->after('nombre');
            }
        });

        Schema::table('iot.ranuras_gabinete', function (Blueprint $table): void {
            if (! Schema::hasColumn('iot.ranuras_gabinete', 'familia_taxonomica_esperada_id')) {
                $table->string('familia_taxonomica_esperada_id')->nullable()->after('numero_ranura');
            }
        });
    }
};
