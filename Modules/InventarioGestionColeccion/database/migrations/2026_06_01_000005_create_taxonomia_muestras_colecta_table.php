<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.muestras_colecta')) {
            Schema::create('taxonomia.muestras_colecta', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('codigo_muestra', 120)->nullable();
                $table->date('fecha_colecta')->nullable();
                $table->string('fecha_verbatim', 120)->nullable();
                $table->uuid('localidad_id')->nullable();
                $table->string('localidad_verbatim', 500)->nullable();
                $table->string('colector', 255)->nullable();
                $table->string('sampling_protocol', 255)->nullable();
                $table->string('estado_revision', 40)->default('pendiente');
                $table->text('motivo_revision')->nullable();
                $table->timestamps();

                $table->foreign('localidad_id')
                    ->references('id')
                    ->on('taxonomia.localidades')
                    ->nullOnDelete();

                $table->index('codigo_muestra');
                $table->index('estado_revision');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.muestras_colecta');
    }
};
