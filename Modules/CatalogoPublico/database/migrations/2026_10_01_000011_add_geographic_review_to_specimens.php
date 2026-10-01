<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Dos envolventes independientes evitan admitir el océano entre continente e islas.
        // Es una alerta de coordenadas, no una corrección ni una atribución de país.
        DB::statement(<<<'SQL'
            ALTER TABLE taxonomia.especimenes ADD COLUMN coordenadas_otras_regiones boolean
            GENERATED ALWAYS AS (COALESCE(
                decimal_latitude BETWEEN -90 AND 90 AND decimal_longitude BETWEEN -180 AND 180
                AND NOT (
                    (decimal_latitude BETWEEN -5.1 AND 1.9 AND decimal_longitude BETWEEN -81.3 AND -75)
                    OR (decimal_latitude BETWEEN -1.6 AND 1.9 AND decimal_longitude BETWEEN -92.1 AND -89.1)
                ), false)) STORED
            SQL);
        DB::statement('CREATE INDEX especimenes_otras_regiones_idx ON taxonomia.especimenes (coordenadas_otras_regiones) WHERE coordenadas_otras_regiones');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE taxonomia.especimenes DROP COLUMN coordenadas_otras_regiones');
    }
};
