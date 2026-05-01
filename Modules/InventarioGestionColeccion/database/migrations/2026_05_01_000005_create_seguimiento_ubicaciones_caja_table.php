<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot.ubicaciones_caja', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('caja_id')
                ->constrained('iot.cajas')
                ->cascadeOnDelete();
            $table->foreignUuid('ranura_gabinete_id')
                ->constrained('iot.ranuras_gabinete')
                ->cascadeOnDelete();
            $table->dateTime('ingresada_en');
            $table->dateTime('retirada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.ubicaciones_caja');
    }
};
