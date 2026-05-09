<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.lecturas_ranura')) {
            Schema::create('iot.lecturas_ranura', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('sincronizacion_id')
                    ->constrained('iot.sincronizaciones_esp32')
                    ->cascadeOnDelete();
                $table->unsignedSmallInteger('numero_ranura');
                $table->string('rfid_detectado', 8)->nullable();
                $table->boolean('es_incongruente')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.lecturas_ranura');
    }
};
