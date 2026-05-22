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

        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."historial_eventos" (
            "id" uuid NOT NULL,
            "tipo_agregado" varchar(255) NOT NULL,
            "agregado_id" uuid NOT NULL,
            "tipo_evento" varchar(255) NOT NULL,
            "datos" jsonb NOT NULL DEFAULT \'[]\',
            "ocurrido_en" timestamp(0) without time zone NOT NULL,
            PRIMARY KEY ("id")
        )');

        DB::statement('CREATE INDEX IF NOT EXISTS "historial_eventos_busqueda_idx"
            ON "prestamos"."historial_eventos" ("tipo_agregado", "agregado_id", "ocurrido_en")');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.historial_eventos');
    }
};
