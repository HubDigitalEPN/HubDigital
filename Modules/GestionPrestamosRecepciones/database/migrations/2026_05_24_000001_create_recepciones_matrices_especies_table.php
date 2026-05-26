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
        DB::statement('CREATE SCHEMA IF NOT EXISTS recepciones');

        Schema::create('recepciones.matrices_especies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('solicitud_id');
            $table->string('tipo_tramite');
            $table->string('estado');
            $table->jsonb('campos_dwc_presentes')->default('{}');
            $table->boolean('identificacion_original_conservada')->default(false);
            $table->timestamps();

            $table->unique('solicitud_id');
            $table->foreign('solicitud_id')
                ->references('id')
                ->on('recepciones.solicitudes_deposito')
                ->onDelete('cascade');
        });

        Schema::create('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('matriz_id');
            $table->string('nombre_cientifico');
            $table->string('nombre_corregido')->nullable();
            $table->string('estado');
            $table->boolean('no_catalogado')->default(false);
            $table->text('motivo_justificacion')->nullable();
            $table->timestamps();

            $table->index('matriz_id');
            $table->foreign('matriz_id')
                ->references('id')
                ->on('recepciones.matrices_especies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.registros_especimen');
        Schema::dropIfExists('recepciones.matrices_especies');
    }
};
