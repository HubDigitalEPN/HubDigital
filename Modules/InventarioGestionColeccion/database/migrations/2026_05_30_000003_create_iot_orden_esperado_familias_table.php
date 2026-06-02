<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS iot');

        if (! Schema::hasTable('iot.orden_esperado_familias')) {
            Schema::create('iot.orden_esperado_familias', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->jsonb('familias')->default('[]');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.orden_esperado_familias');
    }
};
