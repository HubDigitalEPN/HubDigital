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

        Schema::create('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('numero', 10)->unique();

            $table->string('investigador_id');
            $table->string('tipo_tramite');
            $table->string('estado');

            $table->string('origen_recoleccion')->nullable();
            $table->string('situacion_regulatoria')->nullable();
            $table->string('provincia_origen')->nullable();
            $table->boolean('sin_documentacion')->default(false);

            $table->string('nro_permiso_recoleccion')->nullable();
            $table->string('nro_permiso_movilizacion')->nullable();
            $table->string('grupo_animal')->nullable();
            $table->string('localidad')->nullable();
            $table->string('origen_donacion')->nullable();

            $table->jsonb('documentos_adjuntos')->default('[]');
            $table->jsonb('datos_faltantes')->default('[]');

            $table->timestamps();

            $table->index(['investigador_id', 'tipo_tramite', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.solicitudes_deposito');
    }
};
