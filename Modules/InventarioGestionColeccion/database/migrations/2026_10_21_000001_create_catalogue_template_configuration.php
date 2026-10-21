<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomia.configuracion_plantilla_catalogo', function (Blueprint $table): void {
            $table->string('clave', 40)->primary();
            $table->jsonb('definicion');
            $table->string('actualizado_por')->nullable();
            $table->timestamps();
        });
        DB::table('taxonomia.configuracion_plantilla_catalogo')->insert(['clave' => 'catalogo', 'definicion' => json_encode(ConfiguracionPlantillaCatalogo::predeterminada(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void { Schema::dropIfExists('taxonomia.configuracion_plantilla_catalogo'); }
};
