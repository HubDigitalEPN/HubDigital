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
        // 1) Relajar taxon_id a nullable (spec §4.7: muchos especímenes sin identificar).
        //    Hay que dropear primero la FK existente, alterar, y volver a crearla con onDelete restrict.
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropForeign(['taxon_id']);
        });
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->uuid('taxon_id')->nullable()->change();
            $table->foreign('taxon_id')
                ->references('id')
                ->on('taxonomia.taxones')
                ->nullOnDelete();
        });

        // 2) Quitar unicidad de occurrence_id (spec §6: 3.963 IDs se repiten legítimamente).
        //    Hacerlo idempotente: el constraint puede no existir si una migración previa
        //    ya lo dropeó o si nunca se creó. Inspeccionamos pg_constraint antes de tocar.
        $tieneUniqueOccurrence = (bool) DB::selectOne(
            "SELECT 1 FROM pg_constraint
             WHERE conrelid = 'taxonomia.especimenes'::regclass
               AND contype = 'u'
               AND conname = 'taxonomia_especimenes_occurrence_id_unique'"
        );
        if ($tieneUniqueOccurrence) {
            Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
                $table->dropUnique(['occurrence_id']);
            });
        }

        // 3) Renombrar elevation_in_meters → elevation_min_m y agregar elevation_max_m.
        //    Como el sistema arranca vacío, hacemos rename simple.
        if (Schema::hasColumn('taxonomia.especimenes', 'elevation_in_meters')) {
            DB::statement('ALTER TABLE taxonomia.especimenes RENAME COLUMN elevation_in_meters TO elevation_min_m');
        }

        // 4) Añadir todos los campos nuevos del refactor P3.
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            if (! Schema::hasColumn('taxonomia.especimenes', 'elevation_max_m')) {
                $table->decimal('elevation_max_m', 9, 2)->nullable()->after('elevation_min_m');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'taxon_verbatim')) {
                $table->string('taxon_verbatim', 255)->nullable()->after('taxon_id');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'muestra_id')) {
                $table->uuid('muestra_id')->nullable()->after('taxon_verbatim');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'localidad_id')) {
                $table->uuid('localidad_id')->nullable()->after('muestra_id');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'localidad_verbatim')) {
                $table->string('localidad_verbatim', 500)->nullable()->after('locality_name');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'fecha_verbatim')) {
                $table->string('fecha_verbatim', 120)->nullable()->after('fecha_colecta');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'fecha_colecta_fin')) {
                $table->date('fecha_colecta_fin')->nullable()->after('fecha_verbatim');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'individual_count_verbatim')) {
                $table->string('individual_count_verbatim', 120)->nullable()->after('individual_count');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'sex')) {
                $table->string('sex', 40)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'life_stage')) {
                $table->string('life_stage', 40)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'caste')) {
                $table->string('caste', 60)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'type_status')) {
                $table->string('type_status', 120)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'coord_verbatim')) {
                $table->string('coord_verbatim', 255)->nullable()->after('decimal_longitude');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'microhabitat')) {
                $table->string('microhabitat', 255)->nullable()->after('habitat');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'biogeographic_region')) {
                $table->string('biogeographic_region', 120)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'endemic')) {
                $table->boolean('endemic')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'dna_notes')) {
                $table->text('dna_notes')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'occurrence_remarks')) {
                $table->text('occurrence_remarks')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'taxonomic_notes')) {
                $table->text('taxonomic_notes')->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'acta_recepcion')) {
                $table->string('acta_recepcion', 120)->nullable();
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'estado_revision')) {
                $table->string('estado_revision', 40)->default('pendiente');
            }
            if (! Schema::hasColumn('taxonomia.especimenes', 'motivo_revision')) {
                $table->text('motivo_revision')->nullable();
            }
        });

        // 5) Agregar FKs para muestra_id y localidad_id en migración aparte
        //    (Postgres + esquema-prefijado requiere create + alter en pasos separados).
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->foreign('muestra_id')
                ->references('id')
                ->on('taxonomia.muestras_colecta')
                ->nullOnDelete();
            $table->foreign('localidad_id')
                ->references('id')
                ->on('taxonomia.localidades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropForeign(['muestra_id']);
            $table->dropForeign(['localidad_id']);
        });

        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropColumn([
                'elevation_max_m',
                'taxon_verbatim',
                'muestra_id',
                'localidad_id',
                'localidad_verbatim',
                'fecha_verbatim',
                'fecha_colecta_fin',
                'individual_count_verbatim',
                'sex',
                'life_stage',
                'caste',
                'type_status',
                'coord_verbatim',
                'microhabitat',
                'biogeographic_region',
                'endemic',
                'dna_notes',
                'occurrence_remarks',
                'taxonomic_notes',
                'acta_recepcion',
                'estado_revision',
                'motivo_revision',
            ]);
        });

        if (Schema::hasColumn('taxonomia.especimenes', 'elevation_min_m')) {
            DB::statement('ALTER TABLE taxonomia.especimenes RENAME COLUMN elevation_min_m TO elevation_in_meters');
        }

        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropForeign(['taxon_id']);
        });
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->uuid('taxon_id')->nullable(false)->change();
            $table->foreign('taxon_id')
                ->references('id')
                ->on('taxonomia.taxones')
                ->onDelete('restrict');
            $table->unique('occurrence_id');
        });
    }
};
