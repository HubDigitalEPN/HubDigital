<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice trigrama para la búsqueda rápida del catálogo.
 *
 * La caja de búsqueda de la hoja de inventario hacía `ILIKE '%texto%'` sobre 16
 * columnas. Un comodín inicial impide usar cualquier índice btree, así que cada
 * búsqueda era un Seq Scan completo: medido con EXPLAIN ANALYZE, 794 ms de CPU
 * solo para el `count(*)` sobre 48.896 filas — y con el total como subconsulta
 * ese trabajo se paga dos veces en la misma sentencia.
 *
 * La solución es una columna generada que concatena las 16 columnas en
 * minúsculas, más UN índice GIN de trigramas sobre ella. Así el planificador
 * puede resolver `LIKE '%texto%'` por índice.
 *
 * Detalle que obliga a escribir la expresión con `||` y no con `concat_ws()`:
 * `concat_ws` está marcada STABLE (no IMMUTABLE) porque depende de las funciones
 * de salida de cada tipo, y Postgres rechaza expresiones no inmutables en una
 * columna generada. `coalesce`, `||` sobre texto y `lower()` sí son inmutables.
 *
 * La columna es GENERATED ALWAYS: la mantiene Postgres y nadie puede escribirla,
 * de modo que no hay forma de que se desincronice del dato real.
 */
return new class extends Migration
{
    /**
     * Columnas que barre la búsqueda rápida: los campos por los que un curador
     * identifica un espécimen cuando lo tiene en la mano.
     *
     * Todas están creadas por migraciones anteriores de este módulo. Una versión
     * previa incluía además `genus`, `specific_epithet` y `family`: existen en la
     * base de desarrollo pero NINGUNA migración las crea, así que el deploy moría
     * con «column "genus" does not exist» contra un esquema levantado desde cero.
     * Tampoco las lee el código, y en desarrollo solo 26 de 48.896 filas tenían
     * dato, de modo que quitarlas no cambia lo que la búsqueda encuentra.
     */
    private const COLUMNAS = [
        'codigo_catalogo',
        'occurrence_id',
        'catalog_number',
        'old_code',
        'record_number',
        'cardex_liquid_collection_code',
        'taxon_verbatim',
        'colector',
        'localidad',
        'localidad_verbatim',
        'locality_name',
        'municipality',
        'state_province',
    ];

    public function up(): void
    {
        $this->exigirColumnas();

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        if (! Schema::hasColumn('taxonomia.especimenes', 'busqueda_global')) {
            $expresion = 'lower('.implode(" || ' ' || ", array_map(
                fn (string $c) => "coalesce({$c}, '')",
                self::COLUMNAS,
            )).')';

            DB::statement(
                "ALTER TABLE taxonomia.especimenes
                 ADD COLUMN busqueda_global text GENERATED ALWAYS AS ({$expresion}) STORED"
            );
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS taxonomia_especimenes_busqueda_global_trgm
             ON taxonomia.especimenes USING gin (busqueda_global gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS taxonomia.taxonomia_especimenes_busqueda_global_trgm');

        if (Schema::hasColumn('taxonomia.especimenes', 'busqueda_global')) {
            DB::statement('ALTER TABLE taxonomia.especimenes DROP COLUMN busqueda_global');
        }

        // La extensión no se elimina: puede haberla creado o necesitarla otra parte.
    }

    /**
     * Falla antes de tocar nada si el esquema no trae alguna columna esperada.
     *
     * Sin esto el error llega como un SQLSTATE[42703] enterrado en un ALTER TABLE
     * de veinte líneas, en mitad de un deploy. Con esto se lee de un vistazo qué
     * columna falta y en qué base.
     */
    private function exigirColumnas(): void
    {
        $faltantes = array_values(array_filter(
            self::COLUMNAS,
            fn (string $columna) => ! Schema::hasColumn('taxonomia.especimenes', $columna),
        ));

        if ($faltantes !== []) {
            throw new RuntimeException(
                'taxonomia.especimenes no tiene las columnas ['.implode(', ', $faltantes).'], '
                .'necesarias para la búsqueda global. Revisa que las migraciones anteriores '
                .'del módulo se hayan aplicado en esta base antes de reintentar.'
            );
        }
    }
};
