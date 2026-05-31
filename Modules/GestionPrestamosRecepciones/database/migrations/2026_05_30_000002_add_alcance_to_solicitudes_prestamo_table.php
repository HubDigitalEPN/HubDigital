<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "prestamos"."solicitudes_prestamo" ADD COLUMN IF NOT EXISTS "alcance_prestamo" VARCHAR(20) NOT NULL DEFAULT \'nacional\'');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "prestamos"."solicitudes_prestamo" DROP COLUMN IF EXISTS "alcance_prestamo"');
    }
};
