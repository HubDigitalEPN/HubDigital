<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot.ranuras_gabinete', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('gabinete_id')
                ->constrained('iot.gabinetes')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_ranura');
            $table->string('familia_taxonomica_esperada_id')->nullable();
            $table->foreignUuid('caja_actual_id')
                ->nullable()
                ->constrained('iot.cajas')
                ->nullOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['gabinete_id', 'numero_ranura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.ranuras_gabinete');
    }
};
