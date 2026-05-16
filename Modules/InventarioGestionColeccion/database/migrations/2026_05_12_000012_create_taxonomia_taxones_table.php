<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.taxones')) {
            Schema::create('taxonomia.taxones', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('nombre_cientifico', 255);
                $table->string('rango', 50);
                $table->string('autor', 255);
                $table->unsignedSmallInteger('anio_descripcion');
                $table->string('estado', 50)->default('activo');
                $table->uuid('padre_id')->nullable();
                $table->timestamps();

                $table->unique(['nombre_cientifico', 'rango']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.taxones');
    }
};
