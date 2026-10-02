<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('taxonomia.especimenes', 'sampling_protocol')) {
            Schema::table('taxonomia.especimenes', static function (Blueprint $table): void {
                $table->string('sampling_protocol', 255)->nullable();
            });
        }
        // Derivado de la fuente versionada: 33.082 protocolos entre 49.696 filas.
        // La fila de origen es UNIQUE; occurrence_id y old_code confirman su identidad.
        $archivo = fopen('compress.zlib://'.resource_path('data/coleccion-principal-protocolos-20260925.csv.gz'), 'rb');
        if ($archivo === false) throw new RuntimeException('No se pudo abrir la fuente de protocolos de colecta.');
        try {
            fgetcsv($archivo, 0, ',', '"', ''); // Cabecera CSV.
            $lote = [];
            while (($fila = fgetcsv($archivo, 0, ',', '"', '')) !== false) {
                if (count($fila) !== 4) throw new RuntimeException('Fila incompleta en la fuente de protocolos.');
                $lote[] = [(int) $fila[0], $fila[1] !== '' ? $fila[1] : null, $fila[2] !== '' ? $fila[2] : null, $fila[3]];
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
        DB::statement("UPDATE taxonomia.especimenes AS e SET sampling_protocol = fuente.protocolo
            FROM (VALUES {$valores}) AS fuente(fila, occurrence_id, old_code, protocolo)
            WHERE e.fila_origen_excel = fuente.fila
              AND e.occurrence_id IS NOT DISTINCT FROM fuente.occurrence_id
              AND e.old_code IS NOT DISTINCT FROM fuente.old_code
              AND e.sampling_protocol IS NULL", $bindings);
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', static function (Blueprint $table): void {
            $table->dropColumn('sampling_protocol');
        });
    }
};
