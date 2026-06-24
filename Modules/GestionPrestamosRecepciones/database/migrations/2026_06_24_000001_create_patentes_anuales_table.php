<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS "prestamos"."patentes_anuales" (
            "id"         uuid NOT NULL,
            "anio"       integer NOT NULL,
            "codigo"     varchar(255) NOT NULL,
            "curador_id" varchar(255) NOT NULL,
            "created_at" timestamp(0) without time zone,
            "updated_at" timestamp(0) without time zone,
            PRIMARY KEY ("id"),
            UNIQUE ("anio")
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.patentes_anuales');
    }
};
