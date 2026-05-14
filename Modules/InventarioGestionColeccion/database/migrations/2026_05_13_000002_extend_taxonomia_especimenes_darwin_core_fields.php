<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            if (! Schema::hasColumn('taxonomia.especimenes', 'occurrence_id')) {
                $table->string('occurrence_id', 120)->nullable()->after('codigo_catalogo');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'catalog_number')) {
                $table->string('catalog_number', 120)->nullable()->after('occurrence_id');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'old_code')) {
                $table->string('old_code', 120)->nullable()->after('catalog_number');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'cardex_liquid_collection_code')) {
                $table->string('cardex_liquid_collection_code', 120)->nullable()->after('old_code');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'individual_count')) {
                $table->unsignedInteger('individual_count')->nullable()->after('estado');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'preparations')) {
                $table->string('preparations', 120)->nullable()->after('individual_count');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'disposition')) {
                $table->string('disposition', 120)->nullable()->after('preparations');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'occurrence_status')) {
                $table->string('occurrence_status', 120)->nullable()->after('disposition');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'specimen_notes')) {
                $table->text('specimen_notes')->nullable()->after('occurrence_status');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'country')) {
                $table->string('country', 120)->nullable()->after('specimen_notes');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'state_province')) {
                $table->string('state_province', 120)->nullable()->after('country');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'municipality')) {
                $table->string('municipality', 120)->nullable()->after('state_province');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'locality_name')) {
                $table->string('locality_name', 500)->nullable()->after('municipality');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'decimal_latitude')) {
                $table->decimal('decimal_latitude', 10, 7)->nullable()->after('locality_name');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'decimal_longitude')) {
                $table->decimal('decimal_longitude', 10, 7)->nullable()->after('decimal_latitude');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'geodetic_datum')) {
                $table->string('geodetic_datum', 60)->nullable()->after('decimal_longitude');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'elevation_in_meters')) {
                $table->decimal('elevation_in_meters', 9, 2)->nullable()->after('geodetic_datum');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'biome')) {
                $table->string('biome', 120)->nullable()->after('elevation_in_meters');
            }

            if (! Schema::hasColumn('taxonomia.especimenes', 'habitat')) {
                $table->string('habitat', 255)->nullable()->after('biome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropColumn([
                'occurrence_id',
                'catalog_number',
                'old_code',
                'cardex_liquid_collection_code',
                'individual_count',
                'preparations',
                'disposition',
                'occurrence_status',
                'specimen_notes',
                'country',
                'state_province',
                'municipality',
                'locality_name',
                'decimal_latitude',
                'decimal_longitude',
                'geodetic_datum',
                'elevation_in_meters',
                'biome',
                'habitat',
            ]);
        });
    }
};
