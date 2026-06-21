<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.visitantes')) {
            Schema::create('taxonomia.visitantes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('nombre', 255);
                $table->string('contacto', 255)->nullable();
                $table->unsignedInteger('version_acceso')->default(1);
                $table->timestamp('registrado_en');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.visitantes');
    }
};
