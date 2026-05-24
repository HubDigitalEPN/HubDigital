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

        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."prestamos" (
            "id" uuid NOT NULL,
            "acta_prestamo_id" uuid NOT NULL,
            "investigador_id" varchar(255) NOT NULL,
            "estado" varchar(255) NOT NULL DEFAULT \'Activo\',
            "iniciado_en" timestamp(0) without time zone NOT NULL,
            "fecha_fin" timestamp(0) without time zone NOT NULL,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            CONSTRAINT fk_prestamos_acta_prestamo FOREIGN KEY ("acta_prestamo_id")
                REFERENCES "prestamos"."actas_prestamo"("id") ON DELETE CASCADE
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.prestamos');
    }
};
