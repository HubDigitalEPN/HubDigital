<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.cajas')) {
            Schema::create('iot.cajas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('codigo', 100)->unique();
                $table->string('familia_taxonomica_id')->nullable();
                $table->string('nombre')->nullable();
                $table->unsignedSmallInteger('capacidad_maxima')->nullable();
                $table->string('estado', 50);
                // No FK constraint — circular reference with ranuras_gabinete; integridad gestionada por el Dominio
                $table->uuid('ranura_actual_id')->nullable();
                $table->string('codigo_rfid', 8)->nullable()->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.cajas');
    }
};
