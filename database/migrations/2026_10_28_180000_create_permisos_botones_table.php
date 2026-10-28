<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.permisos_botones', function (Blueprint $table): void {
            $table->string('boton', 80);
            $table->string('rol', 40);
            $table->boolean('ver')->default(false);
            $table->boolean('usar')->default(false);
            $table->uuid('actualizado_por')->nullable()->index();
            $table->foreign('actualizado_por')->references('id')->on('usuarios.users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['boton', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.permisos_botones');
    }
};
