<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.unit_trays')) {
            Schema::create('iot.unit_trays', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('caja_id');
                $table->integer('numero');
                $table->jsonb('clasificacion_dominante')->nullable();
                $table->timestamps();

                $table->unique(['caja_id', 'numero']);
                $table->foreign('caja_id')
                    ->references('id')
                    ->on('iot.cajas')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.unit_trays');
    }
};
