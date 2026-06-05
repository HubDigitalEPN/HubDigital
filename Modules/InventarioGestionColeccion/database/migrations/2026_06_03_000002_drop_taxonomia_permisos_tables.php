<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las tablas de permisos y su pivote.
 *
 * Razón: la feature no se usa en la realidad operativa del museo. Los
 * números de permiso real se manejan como campos de texto en la solicitud
 * de depósito del módulo GestionPrestamosRecepciones (`nro_permiso_*`),
 * y no había integración cross-módulo que justificara mantener el modelo
 * normalizado acá.
 *
 * Reversible: down() recrea tablas vacías con la misma estructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('taxonomia.especimen_permiso');
        Schema::dropIfExists('taxonomia.permisos');
    }

    public function down(): void
    {
        if (! Schema::hasTable('taxonomia.permisos')) {
            Schema::create('taxonomia.permisos', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('tipo', 40);
                $table->string('numero', 255)->nullable();
                $table->string('responsable', 255)->nullable();
                $table->text('detalles')->nullable();
                $table->timestamps();
                $table->index('tipo');
            });
        }

        if (! Schema::hasTable('taxonomia.especimen_permiso')) {
            Schema::create('taxonomia.especimen_permiso', function ($table): void {
                $table->uuid('especimen_id');
                $table->uuid('permiso_id');
                $table->primary(['especimen_id', 'permiso_id']);
                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();
                $table->foreign('permiso_id')
                    ->references('id')
                    ->on('taxonomia.permisos')
                    ->cascadeOnDelete();
            });
        }
    }
};
