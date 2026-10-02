<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Derivado de Colección_principal: mantiene el texto completo de la fuente.
        // Una etiqueta pública no única nunca permite identificar una fila por sí sola.
        $archivo = fopen('compress.zlib://'.resource_path('data/coleccion-principal-localidades-20260925.csv.gz'), 'rb');
        if ($archivo === false) throw new RuntimeException('No se pudo abrir la fuente de localidades originales.');
        try {
            fgetcsv($archivo, 0, ',', '"', '');
            $lote = [];
            while (($fila = fgetcsv($archivo, 0, ',', '"', '')) !== false) {
                if (count($fila) !== 4) throw new RuntimeException('Fila incompleta en la fuente de localidades originales.');
                // Las identidades incompletas quedan para revisión curatorial.
                if ($fila[1] === '' || $fila[3] === '' || mb_strlen($fila[3]) > 500) continue;
                $lote[] = [(int) $fila[0], $fila[1], $fila[2] !== '' ? $fila[2] : null, $fila[3]];
                if (count($lote) === 500) { $this->restaurarLote($lote); $lote = []; }
            }
            if ($lote !== []) $this->restaurarLote($lote);
        } finally {
            fclose($archivo);
        }
    }

    private function restaurarLote(array $lote): void
    {
        $valores = implode(', ', array_fill(0, count($lote), '(?::integer, ?::text, ?::text, ?::text)'));
        $bindings = [];
        foreach ($lote as $fila) array_push($bindings, ...$fila);
        DB::statement("UPDATE taxonomia.especimenes AS e SET localidad_verbatim = fuente.localidad
            FROM (VALUES {$valores}) AS fuente(fila, occurrence_id, old_code, localidad)
            WHERE e.fila_origen_excel = fuente.fila
              AND e.occurrence_id = fuente.occurrence_id
              AND e.old_code IS NOT DISTINCT FROM fuente.old_code
              AND (e.localidad_verbatim IS NULL OR btrim(e.localidad_verbatim) = '')", $bindings);
    }

    public function down(): void
    {
        // Una recuperación de datos originales no se revierte borrando el texto.
        // El respaldo anterior a la release permite revertir el estado completo.
    }
};
