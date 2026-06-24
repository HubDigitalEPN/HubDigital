<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $columnas = [
        'state_province_visible',
        'elevation_visible',
        'event_date_visible',
        'caste_visible',
        'life_stage_visible',
    ];

    public function up(): void
    {
        Schema::table('divulgacion.especimenes_divulgables', function (Blueprint $table): void {
            foreach ($this->columnas as $columna) {
                if (! Schema::hasColumn('divulgacion.especimenes_divulgables', $columna)) {
                    $table->boolean($columna)->default(true);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('divulgacion.especimenes_divulgables', function (Blueprint $table): void {
            foreach ($this->columnas as $columna) {
                if (Schema::hasColumn('divulgacion.especimenes_divulgables', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
