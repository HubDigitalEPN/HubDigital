<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos.items_prestamo', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('solicitud_prestamo_id');
            $table->string('especimen_codigo_externo');
            $table->unsignedInteger('cantidad_solicitada');
            $table->json('especimen_snapshot')->nullable();
            $table->text('condiciones_especificas')->nullable();
            $table->timestamps();

            $table->foreign('solicitud_prestamo_id')
                ->references('id')
                ->on('prestamos.solicitudes_prestamo')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.items_prestamo');
    }
};
