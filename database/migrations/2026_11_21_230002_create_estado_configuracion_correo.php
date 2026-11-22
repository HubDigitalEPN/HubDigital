<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.estado_configuracion_correo', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.estado_configuracion_correo');
    }
};
