<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;

return new class extends Migration
{
    public function up(): void
    {
        // Índices selectivos pequeños; no particionar una colección de 50 mil filas.
        DB::statement('CREATE INDEX IF NOT EXISTS portal_taxones_padre_idx ON taxonomia.taxones (padre_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_taxon_orden_idx ON taxonomia.especimenes (taxon_id, fila_origen_excel, id) WHERE NOT coordenadas_otras_regiones');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_punto_idx ON taxonomia.especimenes (decimal_latitude, decimal_longitude, fila_origen_excel, id) WHERE NOT coordenadas_otras_regiones AND decimal_latitude IS NOT NULL AND decimal_longitude IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_occurrence_idx ON taxonomia.especimenes (occurrence_id)');
        foreach (['country', 'state_province', 'locality_name'] as $columna) {
            $nombre = 'portal_especimenes_'.$columna.'_norm_idx';
            DB::statement('CREATE INDEX IF NOT EXISTS '.$nombre.' ON taxonomia.especimenes (('.NormalizacionGeografica::sql($columna).')) WHERE NOT coordenadas_otras_regiones');
        }
    }

    public function down(): void
    {
        foreach (['portal_taxones_padre_idx', 'portal_especimenes_taxon_orden_idx', 'portal_especimenes_punto_idx', 'portal_especimenes_occurrence_idx',
            'portal_especimenes_country_norm_idx', 'portal_especimenes_state_province_norm_idx', 'portal_especimenes_locality_name_norm_idx'] as $indice) {
            DB::statement('DROP INDEX IF EXISTS taxonomia.'.$indice);
        }
    }
};
