<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade el régimen de tenencia con el que un espécimen entró a la colección.
 *
 * Es un eje distinto del de `estado` (circulación: disponible / en_prestamo / …):
 * un espécimen puede ser Temporal y estar en préstamo a la vez. Sin esta columna no
 * habría forma de distinguir un depósito devolutivo de una donación definitiva una
 * vez el material está en la colección, que es justo lo que el curador necesita
 * saber para no dar de baja material ajeno.
 *
 * Nullable a propósito: los especímenes de la carga masiva del catálogo no provienen
 * de un trámite de depósito y no tienen régimen declarado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->string('estado_custodia', 20)
                ->nullable()
                ->after('estado')
                ->comment('Régimen de tenencia del ingreso: Temporal | Permanente | Cuarentena. Null en el material heredado.');

            // El curador filtra por régimen para revisar qué material es devolutivo.
            $table->index('estado_custodia', 'especimenes_estado_custodia_index');
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropIndex('especimenes_estado_custodia_index');
            $table->dropColumn('estado_custodia');
        });
    }
};
