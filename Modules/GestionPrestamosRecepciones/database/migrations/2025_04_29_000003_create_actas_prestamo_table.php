<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestamos.actas_prestamo')) {
            Schema::create('prestamos.actas_prestamo', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('numero_prestamo', 10)->unique();
                $table->uuid('solicitud_prestamo_id')->unique();
                $table->string('estado');
                $table->string('tipo_prestamo');
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->string('pdf_ruta');
                $table->string('pdf_firmado_ruta')->nullable();
                $table->timestamp('firmada_subida_en')->nullable();
                $table->timestamp('validada_en')->nullable();
                $table->string('validada_por')->nullable();
                $table->timestamps();

                $table->foreign('solicitud_prestamo_id')
                    ->references('id')
                    ->on('prestamos.solicitudes_prestamo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.actas_prestamo');
    }
};
