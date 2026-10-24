<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.textos_sistema', function (Blueprint $table): void {
            $table->string('clave', 64)->primary();
            $table->text('original');
            $table->text('contenido');
            $table->uuid('actualizado_por')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('usuarios.textos_sistema'); }
};
