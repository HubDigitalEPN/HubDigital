<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.mensajes_sistema', function (Blueprint $table): void {
            $table->string('clave', 100)->primary();
            $table->text('contenido');
            $table->uuid('actualizado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.mensajes_sistema');
    }
};
