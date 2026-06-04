<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `taxonomia.configuracion_columnas`: overrides de la prioridad de columna
 * (crítica / recomendada / opcional) para cada pantalla del módulo.
 *
 * Singleton lógico por (pantalla, clave_columna). Si no hay row → la pantalla
 * cae al default hardcoded en RegistroColumnas*.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taxonomia.configuracion_columnas')) {
            return;
        }

        Schema::create('taxonomia.configuracion_columnas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('pantalla', 60);
            $table->string('clave_columna', 80);
            $table->string('prioridad', 20);
            $table->string('actualizado_por_id')->nullable();
            $table->timestamps();

            $table->unique(['pantalla', 'clave_columna']);
            $table->index('pantalla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.configuracion_columnas');
    }
};
