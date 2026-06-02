<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de asociación propia del componente de Seguimiento Físico.
 *
 * Referencia al espécimen únicamente por su UUID (especimen_id): la tabla
 * taxonomia.especimenes pertenece a otra capacidad y NO se modifica. Por eso
 * no se declara llave foránea hacia ella; el enlace se mantiene por convención
 * y se resuelve vía el puerto de lectura EspecimenRepositoryInterface.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.unit_tray_especimenes')) {
            Schema::create('iot.unit_tray_especimenes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('unit_tray_id');
                $table->uuid('especimen_id');
                $table->timestamps();

                // Un espécimen vive en a lo sumo un unit tray.
                $table->unique('especimen_id');
                $table->index('unit_tray_id');

                $table->foreign('unit_tray_id')
                    ->references('id')
                    ->on('iot.unit_trays')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.unit_tray_especimenes');
    }
};
