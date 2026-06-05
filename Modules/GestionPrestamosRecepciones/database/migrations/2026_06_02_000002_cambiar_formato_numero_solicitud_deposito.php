<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar la columna para acomodar "MEPN-INV-00001" (14 chars)
        DB::statement('ALTER TABLE "recepciones"."solicitudes_deposito" ALTER COLUMN "numero" TYPE varchar(20)');

        // 2. Renumerar registros con el formato anterior (sol_XXXXXX)
        DB::statement("
            WITH renumbered AS (
                SELECT id,
                       'MEPN-INV-' || LPAD(ROW_NUMBER() OVER (ORDER BY created_at)::text, 5, '0') AS nuevo_numero
                FROM recepciones.solicitudes_deposito
                WHERE numero LIKE 'sol_%'
            )
            UPDATE recepciones.solicitudes_deposito d
            SET numero = r.nuevo_numero
            FROM renumbered r
            WHERE d.id = r.id
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "recepciones"."solicitudes_deposito" ALTER COLUMN "numero" TYPE varchar(10)');
    }
};
