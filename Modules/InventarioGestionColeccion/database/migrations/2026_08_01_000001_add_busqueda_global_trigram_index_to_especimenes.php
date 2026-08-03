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
    /** Las mismas 16 columnas que barre la búsqueda rápida en el repositorio. */
    private const COLUMNAS = [
        'codigo_catalogo',
        'occurrence_id',
        'catalog_number',
        'old_code',
        'record_number',
        'cardex_liquid_collection_code',
        'taxon_verbatim',
        'genus',
        'specific_epithet',
        'family',
        'colector',
        'localidad',
        'localidad_verbatim',
        'locality_name',
        'municipality',
        'state_province',
    ];

    public function up(): void
    {
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
};
