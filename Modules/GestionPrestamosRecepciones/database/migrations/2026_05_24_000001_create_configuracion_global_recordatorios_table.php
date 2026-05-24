<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS recordatorios');

        DB::statement('CREATE TABLE IF NOT EXISTS "recordatorios"."configuracion_global_recordatorios" (
            "id"         uuid NOT NULL,
            "curador_id" varchar(255) NOT NULL,
            "dias_antes" jsonb NOT NULL,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id")
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios.configuracion_global_recordatorios');
    }
};
