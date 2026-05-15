<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.sincronizaciones_esp32')) {
            Schema::create('iot.sincronizaciones_esp32', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('gabinete_id')
                    ->constrained('iot.gabinetes')
                    ->cascadeOnDelete();
                $table->string('estado', 30);
                $table->dateTime('realizada_en');
                $table->unsignedSmallInteger('total_incongruencias')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.sincronizaciones_esp32');
    }
};
