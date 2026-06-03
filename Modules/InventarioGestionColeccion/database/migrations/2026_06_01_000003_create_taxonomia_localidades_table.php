<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.localidades')) {
            Schema::create('taxonomia.localidades', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('nombre_canonico', 255);
                $table->string('rango', 50);
                $table->uuid('padre_id')->nullable();
                $table->decimal('decimal_latitude', 10, 7)->nullable();
                $table->decimal('decimal_longitude', 10, 7)->nullable();
                $table->string('geodetic_datum', 60)->nullable();
                $table->decimal('coordinate_uncertainty_m', 9, 2)->nullable();
                $table->string('lat_lon_verbatim', 255)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('state_province', 120)->nullable();
                $table->string('municipality', 120)->nullable();
                $table->timestamps();

                $table->index('nombre_canonico');
                $table->index(['rango', 'nombre_canonico']);
            });
        }

        Schema::table('taxonomia.localidades', function (Blueprint $table): void {
            $table->foreign('padre_id')
                ->references('id')
                ->on('taxonomia.localidades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.localidades');
    }
};
