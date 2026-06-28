<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica el identificador de negocio del ciclo de préstamo bajo una sola columna
 * `codigo` (formato MEPN-INV-LOAN-AAAA-NNNN), compartida por solicitud, acta y
 * préstamo:
 *  - solicitudes_prestamo.numero_solicitud → codigo (fuente de verdad, único)
 *  - actas_prestamo.numero_prestamo        → codigo (copia)
 *  - prestamos: nueva columna codigo               (copia)
 *
 * Solo cambia la estructura; el backfill de los valores existentes se hace en una
 * migración aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Solicitud: renombrar y ampliar (conserva NOT NULL y UNIQUE existentes).
        DB::statement('ALTER TABLE "prestamos"."solicitudes_prestamo" RENAME COLUMN "numero_solicitud" TO "codigo"');
        DB::statement('ALTER TABLE "prestamos"."solicitudes_prestamo" ALTER COLUMN "codigo" TYPE varchar(40)');

        // Acta: renombrar y ampliar.
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" RENAME COLUMN "numero_prestamo" TO "codigo"');
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" ALTER COLUMN "codigo" TYPE varchar(40)');

        // Préstamo: nueva columna copia (nullable hasta el backfill).
        DB::statement('ALTER TABLE "prestamos"."prestamos" ADD COLUMN IF NOT EXISTS "codigo" varchar(40)');

        // Rellena la columna nueva con el código actual del acta vinculada (no
        // reasigna identificadores; solo deja la app operativa hasta el backfill).
        DB::statement('UPDATE "prestamos"."prestamos" p
            SET "codigo" = a."codigo"
            FROM "prestamos"."actas_prestamo" a
            WHERE p."acta_prestamo_id" = a."id" AND p."codigo" IS NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "prestamos"."prestamos" DROP COLUMN IF EXISTS "codigo"');
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" RENAME COLUMN "codigo" TO "numero_prestamo"');
        DB::statement('ALTER TABLE "prestamos"."solicitudes_prestamo" RENAME COLUMN "codigo" TO "numero_solicitud"');
    }
};
