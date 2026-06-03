<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.identificaciones')) {
            Schema::create('taxonomia.identificaciones', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('especimen_id');
                $table->uuid('taxon_id')->nullable();
                $table->string('identificado_por', 255)->nullable();
                $table->date('fecha_determinacion')->nullable();
                $table->string('calificador', 60)->nullable();
                $table->text('observaciones')->nullable();
                $table->boolean('es_actual')->default(true);
                $table->timestamps();

                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();

                $table->foreign('taxon_id')
                    ->references('id')
                    ->on('taxonomia.taxones')
                    ->nullOnDelete();

                $table->index(['especimen_id', 'es_actual']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.identificaciones');
    }
};
