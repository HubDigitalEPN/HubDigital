<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalización conservadora de campos DwC en taxonomia.especimenes.
 *
 * Aplica los mismos alias maps que NormalizadorCamposDwC (GestionPrestamosRecepciones)
 * sobre los datos ya existentes en BD.
 *
 * - sex       : M/F/hembra/H/Male/nd/? → vocabulario DwC canónico
 * - life_stage: juvenil/ninfa/nympha/nd/adult? → vocabulario DwC canónico
 * - coordenadas: redondea a 4 decimales (no hay comas en BD, solo se redondea)
 *
 * El down() no revierte: la limpieza de datos es irreversible por diseño.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── sex ──────────────────────────────────────────────────────────────
        DB::statement("
            UPDATE taxonomia.especimenes
            SET sex = 'male'
            WHERE LOWER(TRIM(sex)) IN ('m', 'macho', 'male')
              AND sex != 'male'
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET sex = 'female'
            WHERE LOWER(TRIM(sex)) IN ('f', 'hembra', 'h')
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET sex = 'male/female'
            WHERE LOWER(TRIM(sex)) = 'm/f'
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET sex = 'unknown'
            WHERE LOWER(TRIM(sex)) IN ('nd', '?')
        ");

        // ── life_stage ───────────────────────────────────────────────────────
        DB::statement("
            UPDATE taxonomia.especimenes
            SET life_stage = 'juvenile'
            WHERE LOWER(TRIM(life_stage)) = 'juvenil'
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET life_stage = 'nymph'
            WHERE LOWER(TRIM(life_stage)) IN ('ninfa', 'nympha')
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET life_stage = 'adult'
            WHERE LOWER(TRIM(life_stage)) = 'adult?'
        ");

        DB::statement("
            UPDATE taxonomia.especimenes
            SET life_stage = 'unknown'
            WHERE LOWER(TRIM(life_stage)) = 'nd'
        ");

        // ── coordenadas → 4 decimales ────────────────────────────────────────
        DB::statement('
            UPDATE taxonomia.especimenes
            SET decimal_latitude  = ROUND(decimal_latitude, 4),
                decimal_longitude = ROUND(decimal_longitude, 4)
            WHERE decimal_latitude IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Limpieza de datos irreversible por diseño — no se revierte.
    }
};
