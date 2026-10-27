<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $fila = DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->lockForUpdate()->first();
            if ($fila === null) throw new RuntimeException('Falta la configuración de la matriz de depósitos.');
            $definicion = json_decode($fila->definicion, true, 512, JSON_THROW_ON_ERROR);
            $anterior = json_decode(file_get_contents(__DIR__.'/../data/matriz-deposito-v2.json'), true, 512, JSON_THROW_ON_ERROR);
            $nueva = json_decode(file_get_contents(__DIR__.'/../data/matriz-depositantes-v3.json'), true, 512, JSON_THROW_ON_ERROR);
            $originales = array_column($anterior['columnas'], null, 'campo');
            $columnas = array_column($nueva['columnas'], null, 'campo');
            foreach ($definicion['columnas'] as &$columna) {
                $campo = $columna['campo'];
                $columna['plantilla_depositante'] = isset($columnas[$campo]) || ! isset($originales[$campo]);
                if (! isset($columnas[$campo])) continue;
                $definicion['descripciones_guia'][$campo] = array_values(array_unique([
                    $columna['descripcion'], $originales[$campo]['descripcion'], $columnas[$campo]['descripcion'],
                ]));
                // Conservar cambios curatoriales: actualizar solamente los valores originales.
                foreach (['descripcion', 'requisito'] as $propiedad) {
                    if ($columna[$propiedad] === $originales[$campo][$propiedad]) $columna[$propiedad] = $columnas[$campo][$propiedad];
                }
            }
            unset($columna);
            $definicion['etiquetas_guia'] = array_values(array_unique([...$definicion['etiquetas_guia'], ...$nueva['etiquetas_guia']]));
            $definicion['version_plantilla_depositante'] = $nueva['version'];
            DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->update([
                'definicion' => json_encode($definicion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Los requisitos y textos pueden haber sido editados por curaduría; no sobrescribirlos.
    }
};
