<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divulgacion.especimenes_divulgables', function (Blueprint $table): void {
            $table->boolean('publicado')->default(false);
            $table->index('publicado', 'especimenes_divulgables_publicado_idx');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION divulgacion.tiene_filo_confirmado(p_taxon uuid)
            RETURNS boolean LANGUAGE sql STABLE AS $$
                WITH RECURSIVE linaje AS (
                    SELECT id, padre_id, rango, 0 AS nivel
                    FROM taxonomia.taxones WHERE id = p_taxon
                    UNION ALL
                    SELECT padre.id, padre.padre_id, padre.rango, linaje.nivel + 1
                    FROM taxonomia.taxones padre
                    JOIN linaje ON padre.id = linaje.padre_id
                    WHERE linaje.nivel < 20
                )
                SELECT EXISTS (SELECT 1 FROM linaje WHERE rango = 'phylum')
            $$;

            CREATE OR REPLACE FUNCTION divulgacion.actualizar_publicacion_divulgable()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                NEW.publicado := COALESCE((
                    SELECT divulgacion.tiene_filo_confirmado(e.taxon_id)
                    FROM taxonomia.especimenes e WHERE e.id = NEW.especimen_id
                ), false);
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER divulgable_filo_publicacion
            BEFORE INSERT OR UPDATE OF especimen_id, publicado ON divulgacion.especimenes_divulgables
            FOR EACH ROW EXECUTE FUNCTION divulgacion.actualizar_publicacion_divulgable();

            CREATE OR REPLACE FUNCTION divulgacion.refrescar_publicacion_por_taxon()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                UPDATE divulgacion.especimenes_divulgables
                SET publicado = divulgacion.tiene_filo_confirmado(NEW.taxon_id)
                WHERE especimen_id = NEW.id;
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER especimen_filo_publicacion
            AFTER UPDATE OF taxon_id ON taxonomia.especimenes
            FOR EACH ROW WHEN (OLD.taxon_id IS DISTINCT FROM NEW.taxon_id)
            EXECUTE FUNCTION divulgacion.refrescar_publicacion_por_taxon();

            CREATE OR REPLACE FUNCTION divulgacion.refrescar_publicacion_por_jerarquia()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                WITH RECURSIVE afectados AS (
                    SELECT NEW.id, 0 AS nivel
                    UNION ALL
                    SELECT t.id, afectados.nivel + 1
                    FROM taxonomia.taxones t JOIN afectados ON t.padre_id = afectados.id
                    WHERE afectados.nivel < 20
                )
                UPDATE divulgacion.especimenes_divulgables d
                SET publicado = divulgacion.tiene_filo_confirmado(e.taxon_id)
                FROM taxonomia.especimenes e
                WHERE e.id = d.especimen_id AND e.taxon_id IN (SELECT id FROM afectados);
                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER taxon_filo_publicacion
            AFTER UPDATE OF padre_id, rango ON taxonomia.taxones
            FOR EACH ROW WHEN (OLD.padre_id IS DISTINCT FROM NEW.padre_id OR OLD.rango IS DISTINCT FROM NEW.rango)
            EXECUTE FUNCTION divulgacion.refrescar_publicacion_por_jerarquia();

            UPDATE divulgacion.especimenes_divulgables d
            SET publicado = divulgacion.tiene_filo_confirmado(e.taxon_id)
            FROM taxonomia.especimenes e
            WHERE e.id = d.especimen_id;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS taxon_filo_publicacion ON taxonomia.taxones');
        DB::unprepared('DROP TRIGGER IF EXISTS especimen_filo_publicacion ON taxonomia.especimenes');
        DB::unprepared('DROP TRIGGER IF EXISTS divulgable_filo_publicacion ON divulgacion.especimenes_divulgables');
        DB::unprepared('DROP FUNCTION IF EXISTS divulgacion.refrescar_publicacion_por_jerarquia()');
        DB::unprepared('DROP FUNCTION IF EXISTS divulgacion.refrescar_publicacion_por_taxon()');
        DB::unprepared('DROP FUNCTION IF EXISTS divulgacion.actualizar_publicacion_divulgable()');
        DB::unprepared('DROP FUNCTION IF EXISTS divulgacion.tiene_filo_confirmado(uuid)');
        Schema::table('divulgacion.especimenes_divulgables', function (Blueprint $table): void {
            $table->dropIndex('especimenes_divulgables_publicado_idx');
            $table->dropColumn('publicado');
        });
    }
};
