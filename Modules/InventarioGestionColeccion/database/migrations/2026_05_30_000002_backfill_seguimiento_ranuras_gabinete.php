<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sana los gabinetes existentes para reflejar el invariante:
 * un gabinete tiene siempre exactamente las ranuras {1..total_ranuras}.
 *
 * Es aditiva: solo inserta las ranuras faltantes. No borra ni modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.gabinetes') || ! Schema::hasTable('iot.ranuras_gabinete')) {
            return;
        }

        $ahora = now();

        foreach (DB::table('iot.gabinetes')->get(['id', 'total_ranuras']) as $gabinete) {
            $existentes = array_flip(
                DB::table('iot.ranuras_gabinete')
                    ->where('gabinete_id', $gabinete->id)
                    ->pluck('numero_ranura')
                    ->all()
            );

            $nuevas = [];
            for ($numero = 1; $numero <= (int) $gabinete->total_ranuras; $numero++) {
                if (! isset($existentes[$numero])) {
                    $nuevas[] = [
                        'id' => (string) Str::uuid(),
                        'gabinete_id' => $gabinete->id,
                        'numero_ranura' => $numero,
                        'caja_actual_id' => null,
                        'activa' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }

            if ($nuevas !== []) {
                DB::table('iot.ranuras_gabinete')->insert($nuevas);
            }
        }
    }

    public function down(): void
    {
        // No-op: las ranuras son estado de dominio; no se revierten automáticamente.
    }
};
