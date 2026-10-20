<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones.configuracion_matriz', function (Blueprint $table): void {
            $table->string('clave')->primary();
            $table->jsonb('definicion');
            $table->string('actualizado_por')->nullable();
            $table->timestamps();
        });
        $definicion = file_get_contents(__DIR__.'/../data/matriz-deposito-v2.json');
        DB::table('recepciones.configuracion_matriz')->insert([
            'clave' => 'depositos', 'definicion' => $definicion,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones.configuracion_matriz');
    }
};
