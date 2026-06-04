<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening previo al import masivo del Excel (~48.856 filas):
 *
 *  - Relaja `localidad`, `fecha_colecta` y `colector` a nullable. La spec §4.7
 *    los marca nullable; el importador necesita poder insertar filas con
 *    fechas no parseables, sin colector, sin locality_name.
 *  - Agrega `fila_origen_excel` (unsigned int nullable) + unique partial index
 *    para idempotencia. Si se corre el import dos veces, las filas con
 *    `fila_origen_excel` ya presente se saltan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->string('localidad', 255)->nullable()->change();
            $table->date('fecha_colecta')->nullable()->change();
            $table->string('colector', 255)->nullable()->change();

            if (! Schema::hasColumn('taxonomia.especimenes', 'fila_origen_excel')) {
                $table->unsignedInteger('fila_origen_excel')->nullable();
            }
        });

        // Unique parcial sólo cuando fila_origen_excel no es null (permite múltiples
        // especímenes manuales sin fila origen sin colisionar). PostgreSQL-specific.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS taxonomia_especimenes_fila_origen_excel_unique
             ON taxonomia.especimenes (fila_origen_excel)
             WHERE fila_origen_excel IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS taxonomia.taxonomia_especimenes_fila_origen_excel_unique'
        );

        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            if (Schema::hasColumn('taxonomia.especimenes', 'fila_origen_excel')) {
                $table->dropColumn('fila_origen_excel');
            }
            $table->string('localidad', 255)->nullable(false)->change();
            $table->date('fecha_colecta')->nullable(false)->change();
            $table->string('colector', 255)->nullable(false)->change();
        });
    }
};
