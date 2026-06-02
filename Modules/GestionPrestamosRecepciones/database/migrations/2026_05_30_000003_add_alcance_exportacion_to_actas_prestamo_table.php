<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" ADD COLUMN IF NOT EXISTS "alcance_prestamo" VARCHAR(20) NOT NULL DEFAULT \'nacional\'');
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" ADD COLUMN IF NOT EXISTS "documento_exportacion_ruta" VARCHAR(500)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" DROP COLUMN IF EXISTS "alcance_prestamo"');
        DB::statement('ALTER TABLE "prestamos"."actas_prestamo" DROP COLUMN IF EXISTS "documento_exportacion_ruta"');
    }
};
