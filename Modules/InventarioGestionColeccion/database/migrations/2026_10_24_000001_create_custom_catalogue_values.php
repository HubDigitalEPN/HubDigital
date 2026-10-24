<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomia.valores_columnas_especimen', function (Blueprint $table): void {
            $table->uuid('especimen_id'); $table->string('campo', 160); $table->text('valor')->nullable();
            $table->uuid('actualizado_por')->nullable(); $table->timestamp('updated_at')->nullable();
            $table->primary(['especimen_id', 'campo']);
            $table->foreign('especimen_id')->references('id')->on('taxonomia.especimenes');
        });
        Schema::create('taxonomia.historial_columnas_especimen', function (Blueprint $table): void {
            $table->bigIncrements('id'); $table->uuid('especimen_id'); $table->string('campo', 160);
            $table->text('anterior')->nullable(); $table->text('nuevo')->nullable();
            $table->uuid('actualizado_por')->nullable(); $table->timestamp('created_at');
            $table->index('campo');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('taxonomia.historial_columnas_especimen');
        Schema::dropIfExists('taxonomia.valores_columnas_especimen');
    }
};
