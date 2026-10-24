<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.configuracion_catalogo', function (Blueprint $table): void {
            $table->string('clave')->primary();
            $table->boolean('habilitado')->default(false);
            $table->uuid('actualizado_por')->nullable();
            $table->timestamps();
        });
        DB::table('usuarios.configuracion_catalogo')->insert(['clave' => 'descargas_autoservicio', 'habilitado' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void { Schema::dropIfExists('usuarios.configuracion_catalogo'); }
};
