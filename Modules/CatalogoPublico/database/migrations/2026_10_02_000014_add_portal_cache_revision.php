<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE divulgacion.portal_cache_revision (id smallint PRIMARY KEY CHECK (id = 1), version bigint NOT NULL DEFAULT 1)');
        DB::statement('INSERT INTO divulgacion.portal_cache_revision (id, version) VALUES (1, 1)');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION divulgacion.actualizar_revision_portal() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE divulgacion.portal_cache_revision SET version = version + 1 WHERE id = 1;
                RETURN NULL;
            END;
            $$;
            SQL);
        foreach (['taxonomia.especimenes', 'divulgacion.especimenes_divulgables', 'taxonomia.taxones',
            'taxonomia.muestras_colecta', 'divulgacion.imagenes_taxonomicas'] as $tabla) {
            DB::statement('CREATE TRIGGER portal_revision_datos AFTER INSERT OR UPDATE OR DELETE ON '.$tabla.' FOR EACH STATEMENT EXECUTE FUNCTION divulgacion.actualizar_revision_portal()');
        }
    }

    public function down(): void
    {
        foreach (['taxonomia.especimenes', 'divulgacion.especimenes_divulgables', 'taxonomia.taxones',
            'taxonomia.muestras_colecta', 'divulgacion.imagenes_taxonomicas'] as $tabla) {
            DB::statement('DROP TRIGGER IF EXISTS portal_revision_datos ON '.$tabla);
        }
        DB::statement('DROP FUNCTION IF EXISTS divulgacion.actualizar_revision_portal()');
        DB::statement('DROP TABLE IF EXISTS divulgacion.portal_cache_revision');
    }
};
