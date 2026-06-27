<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('divulgacion.imagenes_por_defecto')) {
            return;
        }

        Schema::create('divulgacion.imagenes_por_defecto', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nivel');
            $table->string('valor_taxon');
            $table->uuid('imagen_id');
            $table->timestamps();

            $table->unique(['nivel', 'valor_taxon']);

            $table->foreign('imagen_id')
                ->references('id')
                ->on('divulgacion.imagenes_taxonomicas')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divulgacion.imagenes_por_defecto');
    }
};
