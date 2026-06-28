<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de los registros existentes al nuevo código unificado
 * MEPN-INV-LOAN-{AÑO}-{NNNN}.
 *
 * Recorre las solicitudes ordenadas por antigüedad (created_at), asigna un
 * correlativo global continuo de 4 dígitos usando el año de creación de cada
 * solicitud y propaga el mismo código a su acta y a su préstamo. Deja el
 * contador en el último número asignado para que las nuevas solicitudes
 * continúen la serie.
 *
 * ADVERTENCIA: reescribe identificadores ya emitidos (incluidas actas firmadas).
 * El PDF impreso conserva el número anterior; la BD pasa al formato nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $solicitudes = DB::table('prestamos.solicitudes_prestamo')
                ->orderByRaw('"created_at" ASC NULLS LAST')
                ->orderBy('id')
                ->get(['id', 'created_at']);

            $contador = 0;

            foreach ($solicitudes as $solicitud) {
                $contador++;

                $anio = $solicitud->created_at !== null
                    ? (int) date('Y', strtotime((string) $solicitud->created_at))
                    : (int) date('Y');

                $codigo = sprintf('MEPN-INV-LOAN-%d-%04d', $anio, $contador);

                DB::table('prestamos.solicitudes_prestamo')
                    ->where('id', $solicitud->id)
                    ->update(['codigo' => $codigo]);

                $actaIds = DB::table('prestamos.actas_prestamo')
                    ->where('solicitud_prestamo_id', $solicitud->id)
                    ->pluck('id');

                if ($actaIds->isNotEmpty()) {
                    DB::table('prestamos.actas_prestamo')
                        ->whereIn('id', $actaIds)
                        ->update(['codigo' => $codigo]);

                    DB::table('prestamos.prestamos')
                        ->whereIn('acta_prestamo_id', $actaIds)
                        ->update(['codigo' => $codigo]);
                }
            }

            DB::table('prestamos.secuencias_codigo_prestamo')
                ->where('id', 1)
                ->update(['ultimo_numero' => $contador, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // No reversible: los identificadores legados originales no se conservan.
        // Se reinicia el contador para no dejar estado inconsistente si se re-aplica.
        DB::table('prestamos.secuencias_codigo_prestamo')
            ->where('id', 1)
            ->update(['ultimo_numero' => 0, 'updated_at' => now()]);
    }
};
