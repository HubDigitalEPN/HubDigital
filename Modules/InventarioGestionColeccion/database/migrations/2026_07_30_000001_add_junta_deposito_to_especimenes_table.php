<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Junta explícita entre un espécimen y el trámite de depósito que lo trajo.
 *
 * Hasta ahora el único vínculo era el `codigo_catalogo` derivado por string
 * (`{numeroSolicitud}-{indice:4}`). Eso trae tres problemas que estas columnas cierran:
 *
 *  1. La idempotencia del ingreso dependía de la POSICIÓN de la fila dentro del array
 *     de la matriz. Si el curador añade o borra una fila entre dos aprobaciones, todos
 *     los índices se corren y el lote entero se duplica. `registro_deposito_id` apunta
 *     al uuid estable de `recepciones.registros_especimen` y lo hace imposible.
 *  2. `recepciones.solicitudes_deposito.numero` ya se reescribió dos veces por
 *     migraciones de datos sin propagarse aquí. `solicitud_deposito_id` (uuid) sobrevive
 *     a la tercera renumeración, que llegará.
 *  3. `codigo_catalogo` no puede llevar índice único: el Excel heredado dejó 3.963
 *     códigos duplicados. Sobre estas columnas sí se puede, con únicos parciales.
 *
 * `numero_solicitud_deposito` se denormaliza A PROPÓSITO: es el único rastro que
 * sobrevive si la solicitud se borra. Ya ocurrió — hay 13 especímenes cuya solicitud
 * `MEPN-INV-DEP-00001` ya no existe.
 *
 * `codigo_catalogo` se sigue generando igual y no cambia de significado para el usuario:
 * pasa de ser clave a ser etiqueta legible.
 *
 * ADITIVA: todas las columnas nullable, ningún tipo existente se altera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            if (! Schema::hasColumn('taxonomia.especimenes', 'registro_deposito_id')) {
                $table->uuid('registro_deposito_id')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'solicitud_deposito_id')) {
                $table->uuid('solicitud_deposito_id')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'indice_matriz')) {
                $table->unsignedInteger('indice_matriz')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'numero_solicitud_deposito')) {
                $table->string('numero_solicitud_deposito', 30)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'tipo_tramite_origen')) {
                $table->string('tipo_tramite_origen', 20)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'ingresado_en')) {
                $table->timestampTz('ingresado_en')->nullable();
            }
        });

        // Clave de idempotencia real del ingreso de depósito. Parcial porque los ~48k
        // especímenes del import del catálogo no vienen de ningún depósito.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS taxonomia_especimenes_registro_deposito_unique
             ON taxonomia.especimenes (registro_deposito_id)
             WHERE registro_deposito_id IS NOT NULL'
        );

        // Segunda defensa: dentro de un mismo depósito, un índice de matriz es único.
        // Su columna líder cubre además las consultas por lote, así que no hace falta
        // un índice suelto sobre solicitud_deposito_id.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS taxonomia_especimenes_solicitud_indice_unique
             ON taxonomia.especimenes (solicitud_deposito_id, indice_matriz)
             WHERE solicitud_deposito_id IS NOT NULL'
        );

        // NO unique: 3.963 codigo_catalogo duplicados del Excel heredado lo impiden.
        // Pero es la columna de tres rutas `whereIn` sobre 48.883 filas sin ningún
        // índice, y del backfill. Un btree normal las saca del seq scan.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS taxonomia_especimenes_codigo_catalogo_index
             ON taxonomia.especimenes (codigo_catalogo)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS taxonomia.taxonomia_especimenes_registro_deposito_unique');
        DB::statement('DROP INDEX IF EXISTS taxonomia.taxonomia_especimenes_solicitud_indice_unique');
        DB::statement('DROP INDEX IF EXISTS taxonomia.taxonomia_especimenes_codigo_catalogo_index');

        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            foreach ([
                'registro_deposito_id',
                'solicitud_deposito_id',
                'indice_matriz',
                'numero_solicitud_deposito',
                'tipo_tramite_origen',
                'ingresado_en',
            ] as $columna) {
                if (Schema::hasColumn('taxonomia.especimenes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
