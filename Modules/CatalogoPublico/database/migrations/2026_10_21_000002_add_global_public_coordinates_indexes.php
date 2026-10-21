<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La señal de revisión geográfica permanece; no es un permiso de divulgación.
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_geo_global_idx ON taxonomia.especimenes (fila_origen_excel, id) WHERE decimal_latitude BETWEEN -90 AND 90 AND decimal_longitude BETWEEN -180 AND 180');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_taxon_global_idx ON taxonomia.especimenes (taxon_id, fila_origen_excel, id) WHERE decimal_latitude BETWEEN -90 AND 90 AND decimal_longitude BETWEEN -180 AND 180');
        DB::statement('CREATE INDEX IF NOT EXISTS portal_especimenes_punto_global_idx ON taxonomia.especimenes (decimal_latitude, decimal_longitude, fila_origen_excel, id) WHERE decimal_latitude BETWEEN -90 AND 90 AND decimal_longitude BETWEEN -180 AND 180');
    }

    public function down(): void
    {
        foreach (['portal_especimenes_geo_global_idx', 'portal_especimenes_taxon_global_idx', 'portal_especimenes_punto_global_idx'] as $nombre) DB::statement('DROP INDEX IF EXISTS taxonomia.'.$nombre);
    }
};
