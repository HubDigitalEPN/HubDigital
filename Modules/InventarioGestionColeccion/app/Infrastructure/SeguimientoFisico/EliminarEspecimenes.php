<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico;

use App\Support\BotonesSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class EliminarEspecimenes
{
    /** Borrado físico en cualquier estado; conserva expedientes y documentos. */
    public function eliminar(array $ids): array
    {
        BotonesSistema::autorizar('inventario.eliminar');
        Validator::make(['ids' => $ids], ['ids' => ['required', 'array'], 'ids.*' => ['required', 'uuid']])->validate();
        $ids = array_values(array_unique($ids));
        return DB::transaction(function () use ($ids): array {
            $filas = DB::table('taxonomia.especimenes')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'codigo_catalogo']);
            $encontrados = $filas->pluck('id')->all();
            if (!$encontrados) return [];
            foreach ($filas as $fila) DB::table('taxonomia.eliminaciones_especimenes')->insert([
                'id' => (string) Str::uuid(), 'especimen_id' => $fila->id, 'codigo_catalogo' => $fila->codigo_catalogo,
                'eliminado_por' => auth()->id(), 'eliminado_en' => now(),
            ]);
            // Datos dependientes con FK histórica NO ACTION y ubicación física
            // sin FK. Las otras relaciones aplican CASCADE o SET NULL en PostgreSQL.
            foreach (['taxonomia.valores_columnas_especimen', 'taxonomia.localidades_desglose_respaldo',
                'taxonomia.revision_nombres_cientificos', 'iot.unit_tray_especimenes'] as $tabla) {
                if (Schema::hasTable($tabla)) DB::table($tabla)->whereIn('especimen_id', $encontrados)->delete();
            }
            $borrados = DB::table('taxonomia.especimenes')->whereIn('id', $encontrados)->delete();
            if ($borrados !== count($encontrados)) throw new \RuntimeException('La selección cambió durante el borrado.');
            return $encontrados;
        });
    }
}
