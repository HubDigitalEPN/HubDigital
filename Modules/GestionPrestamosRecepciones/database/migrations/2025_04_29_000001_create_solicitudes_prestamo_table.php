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

        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."solicitudes_prestamo" (
            "id" uuid NOT NULL,
            "numero_solicitud" varchar(10) NOT NULL UNIQUE,
            "investigador_id" varchar(255) NOT NULL,
            "estado" varchar(255) NOT NULL DEFAULT \'Borrador\',
            "titulo_estudio" varchar(255),
            "institucion_adscripcion" varchar(255),
            "linea_investigacion" varchar(255),
            "proposito_prestamo" text,
            "duracion_propuesta_meses" integer,
            "justificacion_extendida" text,
            "comentario_curador" text,
            "enviada_en" timestamp(0) without time zone,
            "resuelta_en" timestamp(0) without time zone,
            "resuelta_por" varchar(255),
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id")
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.solicitudes_prestamo');
    }
};
