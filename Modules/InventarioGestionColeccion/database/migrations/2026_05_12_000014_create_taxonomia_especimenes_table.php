<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.especimenes')) {
            Schema::create('taxonomia.especimenes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('codigo_catalogo', 100)->unique();
                $table->uuid('taxon_id');
                $table->string('localidad', 255);
                $table->date('fecha_colecta');
                $table->string('colector', 255);
                $table->uuid('entidad_depositante_id')->nullable();
                $table->string('estado', 50)->default('disponible');
                $table->timestamps();

                $table->foreign('taxon_id')
                    ->references('id')
                    ->on('taxonomia.taxones')
                    ->onDelete('restrict');

                $table->foreign('entidad_depositante_id')
                    ->references('id')
                    ->on('taxonomia.entidades_depositantes')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.especimenes');
    }
};
