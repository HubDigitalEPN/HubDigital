<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop del unique constraint sobre `codigo_catalogo` en `taxonomia.especimenes`.
 *
 * Razón (decisión P0.1): el Excel del catálogo trae 3.963 occurrenceIDs
 * duplicados. La unicidad canónica del espécimen es el UUID interno; el
 * `codigo_catalogo` es solo un alias preservado del CSV. La unicidad GBIF
 * se construye en el exportador como `MEPN:INV:{uuid}`, no en BD.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'taxonomia_especimenes_codigo_catalogo_unique';

    public function up(): void
    {
        DB::statement('ALTER TABLE taxonomia.especimenes DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
    }

    public function down(): void
    {
        // No re-creamos el constraint en down() porque los datos importados
        // del Excel tienen duplicados legítimos; recrearlo fallaría.
    }
};
