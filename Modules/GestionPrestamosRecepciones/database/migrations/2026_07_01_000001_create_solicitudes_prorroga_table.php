<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS prestamos');

        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."solicitudes_prorroga" (
            "id" uuid NOT NULL,
            "prestamo_id" uuid NOT NULL,
            "fecha_propuesta" timestamp(0) without time zone NOT NULL,
            "justificacion" text NOT NULL,
            "estado" varchar(255) NOT NULL DEFAULT \'solicitada\',
            "solicitada_en" timestamp(0) without time zone NOT NULL,
            "comentario_resolucion" text,
            "resuelta_en" timestamp(0) without time zone,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT fk_solicitudes_prorroga_prestamo FOREIGN KEY ("prestamo_id")
                REFERENCES "prestamos"."prestamos"("id") ON DELETE CASCADE
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.solicitudes_prorroga');
    }
};
