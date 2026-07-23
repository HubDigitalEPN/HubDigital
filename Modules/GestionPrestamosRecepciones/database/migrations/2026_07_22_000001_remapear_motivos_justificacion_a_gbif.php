<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remapea los motivos de justificación taxonómica antiguos al motivo neutro
 * "Nombre no presente en el catálogo GBIF".
 *
 * Los textos anteriores ("Es una especie nueva", "No listada en catálogo")
 * afirmaban novedad taxonómica —una aserción fuerte que no se sostiene solo por
 * un nombre que no matcheó— o hablaban de un "catálogo" ambiguo; el proceso en
 * realidad valida contra GBIF. Preserva los registros existentes (no borra
 * datos), solo actualiza el texto del motivo.
 *
 * Irreversible: el texto original no es reconstruible a partir del neutro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recepciones.registros_especimen')
            ->whereIn('motivo_justificacion', ['Es una especie nueva', 'No listada en catálogo'])
            ->update(['motivo_justificacion' => 'Nombre no presente en el catálogo GBIF']);
    }

    public function down(): void
    {
        // Irreversible: el texto original no es recuperable desde el valor neutro.
    }
};
