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

        if (Schema::hasTable('recepciones.recepcion_lotes')) {
            return;
        }

        Schema::create('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('solicitud_deposito_id')->unique();
            $table->string('codigo_qr')->unique();
            $table->string('tipo_tramite');
            $table->string('estado');
            $table->string('estado_coleccion')->nullable();
            $table->string('motivo_fallo')->nullable();
            $table->string('accion_correctiva')->nullable();
            $table->jsonb('items_verificacion')->default('[]');
            $table->jsonb('observaciones')->default('[]');
            $table->jsonb('acta_recepcion')->nullable();
            $table->timestamps();

            $table->index('solicitud_deposito_id');
            $table->foreign('solicitud_deposito_id')
                ->references('id')
                ->on('recepciones.solicitudes_deposito')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.recepcion_lotes');
    }
};
