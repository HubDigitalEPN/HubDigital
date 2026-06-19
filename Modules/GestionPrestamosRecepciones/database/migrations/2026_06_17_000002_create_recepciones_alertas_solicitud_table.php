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

        if (! Schema::hasTable('recepciones.alertas_solicitud')) {
            Schema::create('recepciones.alertas_solicitud', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('solicitud_deposito_id');
                $table->string('tipo');
                $table->text('justificacion_investigador')->nullable();
                $table->string('estado_revision');
                $table->timestamps();

                $table->index('solicitud_deposito_id');
                $table->foreign('solicitud_deposito_id')
                    ->references('id')
                    ->on('recepciones.solicitudes_deposito')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.alertas_solicitud');
    }
};
