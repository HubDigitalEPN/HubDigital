<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla contadora de una sola fila para el correlativo global de códigos de
 * préstamo (MEPN-INV-LOAN-AAAA-NNNN). La fila se siembra en 0; el adaptador la
 * incrementa con lockForUpdate para reservar cada número de forma atómica.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS prestamos');

        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."secuencias_codigo_prestamo" (
            "id" smallint NOT NULL DEFAULT 1,
            "ultimo_numero" integer NOT NULL DEFAULT 0,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT "secuencias_codigo_prestamo_fila_unica" CHECK ("id" = 1)
        )');

        DB::statement('INSERT INTO "prestamos"."secuencias_codigo_prestamo" ("id", "ultimo_numero", "created_at", "updated_at")
            VALUES (1, 0, now(), now())
            ON CONFLICT ("id") DO NOTHING');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.secuencias_codigo_prestamo');
    }
};
