<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.entidades_depositantes')) {
            Schema::create('taxonomia.entidades_depositantes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('nombre', 255)->unique();
                $table->string('tipo', 50);
                $table->string('contacto', 255);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.entidades_depositantes');
    }
};
