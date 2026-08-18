<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lado inverso de la junta depósito ↔ colección: qué espécimen produjo esta fila.
 *
 * Hasta ahora el vínculo solo existía en una dirección y de forma implícita (había que
 * recalcular el `codigo_catalogo` derivado para encontrarlo). Con esta columna la
 * pregunta "¿esta fila de la matriz ya está en la colección?" se responde con una
 * lectura, que es lo que necesita la guarda que impide borrar una matriz ya ingresada.
 *
 * La FK cruza de esquema (`recepciones` → `taxonomia`) siguiendo la regla del proyecto:
 * la declara el módulo dueño de la tabla que referencia, en dirección Customer →
 * Supplier. La inversa (`taxonomia` → `recepciones`) NO se declara: invertiría la
 * dependencia entre bounded contexts, y un borrado en cascada destruiría especímenes
 * al eliminar una matriz — justo el escenario que dejó 13 especímenes huérfanos, pero
 * perdiendo el patrimonio documental en vez de dejar rastro.
 *
 * `nullOnDelete`: si un espécimen se borra del inventario, la fila de la matriz
 * sobrevive con su declaración original intacta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.registros_especimen', 'especimen_id')) {
            return;
        }

        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->uuid('especimen_id')->nullable();

            $table->foreign('especimen_id')
                ->references('id')
                ->on('taxonomia.especimenes')
                ->nullOnDelete();
        });

        // Un espécimen proviene como mucho de una fila de matriz. Parcial porque lo
        // normal es que la fila aún no haya ingresado a la colección.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS recepciones_registros_especimen_especimen_unique
             ON recepciones.registros_especimen (especimen_id)
             WHERE especimen_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS recepciones.recepciones_registros_especimen_especimen_unique');

        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            if (Schema::hasColumn('recepciones.registros_especimen', 'especimen_id')) {
                $table->dropForeign(['especimen_id']);
                $table->dropColumn('especimen_id');
            }
        });
    }
};
