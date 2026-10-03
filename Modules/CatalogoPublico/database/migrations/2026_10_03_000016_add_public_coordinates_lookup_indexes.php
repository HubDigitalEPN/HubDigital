<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Orden global LIMIT 6 y unión por UUID sin incluir los registros pendientes.
        // No modifica coordenadas, identidad, permisos ni publicación persistida.
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_geo_orden_idx ON taxonomia.especimenes (fila_origen_excel, id) WHERE NOT coordenadas_otras_regiones AND decimal_latitude IS NOT NULL AND decimal_longitude IS NOT NULL AND decimal_latitude BETWEEN -90 AND 90 AND decimal_longitude BETWEEN -180 AND 180');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_divulgables_geo_idx ON divulgacion.especimenes_divulgables (especimen_id) WHERE publicado AND decimal_latitude_visible IS TRUE AND decimal_longitude_visible IS TRUE');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS divulgacion.portal_divulgables_geo_idx');
        DB::statement('DROP INDEX IF EXISTS taxonomia.portal_especimenes_geo_orden_idx');
    }
};
