<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot.alertas_ubicacion', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('caja_id')
                ->constrained('iot.cajas')
                ->cascadeOnDelete();
            $table->string('tipo', 60);
            $table->string('estado', 30);
            $table->json('datos_contexto')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.alertas_ubicacion');
    }
};
