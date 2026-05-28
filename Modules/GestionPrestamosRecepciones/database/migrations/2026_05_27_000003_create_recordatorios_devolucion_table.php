<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."recordatorios_devolucion" (
            "id"                     uuid NOT NULL,
            "prestamo_id"            uuid NOT NULL,
            "dias_antes_vencimiento" integer NOT NULL,
            "fecha_programada"       timestamp(0) without time zone NOT NULL,
            "created_at"             timestamp(0) without time zone,
            "updated_at"             timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT fk_recordatorio_prestamo
                FOREIGN KEY ("prestamo_id")
                REFERENCES "prestamos"."prestamos"("id") ON DELETE CASCADE
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.recordatorios_devolucion');
    }
};
