<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."verificaciones_entrega_prestamo" (
            "id" uuid NOT NULL,
            "prestamo_id" uuid NOT NULL,
            "estado_envio" varchar(50) NOT NULL,
            "observaciones" jsonb NOT NULL DEFAULT \'[]\',
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT fk_verificaciones_prestamo FOREIGN KEY ("prestamo_id")
                REFERENCES "prestamos"."prestamos"("id") ON DELETE CASCADE
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.verificaciones_entrega_prestamo');
    }
};
