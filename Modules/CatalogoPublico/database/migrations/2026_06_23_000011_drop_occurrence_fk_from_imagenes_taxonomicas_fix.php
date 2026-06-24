<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La migración 000010 intentó eliminar la FK con un nombre incorrecto
     * (imagenes_taxonomicas_occurrence_id_foreign), por lo que el constraint
     * real —que Laravel nombró con el schema incluido— sobrevivió y seguía
     * bloqueando la subida de imágenes. Aquí se elimina con el nombre correcto.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE divulgacion.imagenes_taxonomicas DROP CONSTRAINT IF EXISTS divulgacion_imagenes_taxonomicas_occurrence_id_foreign');
    }

    public function down(): void
    {
        // No se recrea: la FK era incorrecta por diseño.
    }
};
