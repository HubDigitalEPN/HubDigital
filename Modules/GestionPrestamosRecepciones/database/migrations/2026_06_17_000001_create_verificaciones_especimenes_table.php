<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."verificaciones_especimenes" (
            "id" uuid NOT NULL,
            "prestamo_id" uuid NOT NULL,
            "tipo" varchar(50) NOT NULL,
            "resultado" varchar(50) NOT NULL,
            "observaciones" jsonb NOT NULL DEFAULT \'[]\',
            "observacion_general" text,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT uq_verificaciones_especimenes_prestamo_tipo UNIQUE ("prestamo_id", "tipo"),
            CONSTRAINT fk_verificaciones_especimenes_prestamo FOREIGN KEY ("prestamo_id")
                REFERENCES "prestamos"."prestamos"("id") ON DELETE CASCADE
        )');

        // Copia hacia adelante de las verificaciones de recepción existentes (no se borra la tabla origen).
        if (Schema::hasTable('prestamos.verificaciones_entrega_prestamo')) {
            DB::statement('INSERT INTO "prestamos"."verificaciones_especimenes"
                ("id", "prestamo_id", "tipo", "resultado", "observaciones", "observacion_general", "created_at", "updated_at")
                SELECT "id", "prestamo_id", \'recepcion\', "estado_envio", "observaciones", NULL, "created_at", "updated_at"
                FROM "prestamos"."verificaciones_entrega_prestamo"
                ON CONFLICT ("prestamo_id", "tipo") DO NOTHING');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.verificaciones_especimenes');
    }
};
