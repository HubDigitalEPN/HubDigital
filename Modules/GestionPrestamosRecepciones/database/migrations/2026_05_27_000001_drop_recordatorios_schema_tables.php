<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS "recordatorios"."recordatorios_devolucion"');
        DB::statement('DROP TABLE IF EXISTS "recordatorios"."configuracion_global_recordatorios"');
        DB::statement('DROP SCHEMA IF EXISTS "recordatorios"');
    }

    public function down(): void
    {
        // Irreversible — el schema correcto es prestamos
    }
};
