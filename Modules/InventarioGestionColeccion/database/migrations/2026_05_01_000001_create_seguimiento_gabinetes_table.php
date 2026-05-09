<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.gabinetes')) {
            Schema::create('iot.gabinetes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('codigo', 100)->unique();
                $table->string('nombre');
                $table->unsignedSmallInteger('total_ranuras');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.gabinetes');
    }
};
