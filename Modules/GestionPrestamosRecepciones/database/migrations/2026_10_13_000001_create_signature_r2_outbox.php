<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones.firmas_r2_pendientes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('solicitud_id')->index();
            $table->unsignedInteger('documento_version');
            $table->string('ruta', 500)->unique();
            $table->string('ruta_anterior', 500)->nullable();
            $table->string('sha256', 64);
            $table->unsignedInteger('bytes');
            $table->longText('contenido_cifrado')->nullable();
            $table->string('estado', 24)->default('pendiente')->index();
            $table->unsignedInteger('intentos')->default(0);
            $table->string('ultimo_error_tipo', 250)->nullable();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.firmas_r2_pendientes');
    }
};
