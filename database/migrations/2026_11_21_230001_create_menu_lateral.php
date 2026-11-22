<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.menu_lateral', function (Blueprint $table): void {
            $table->string('clave', 80)->primary();
            $table->jsonb('configuracion');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios.menu_lateral');
    }
};
