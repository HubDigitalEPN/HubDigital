<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La FK occurrence_id apuntaba a divulgacion.especimenes (tabla no usada/ vacía),
     * lo que bloqueaba toda subida de imágenes. El occurrence_id real vive en
     * taxonomia.especimenes, donde la columna no es única (no admite FK). Se elimina.
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
