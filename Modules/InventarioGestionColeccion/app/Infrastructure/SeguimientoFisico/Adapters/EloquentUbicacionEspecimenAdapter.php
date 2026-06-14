<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\UbicacionEspecimenPort;

/**
 * Implementación del puerto de ubicación: resuelve en una sola consulta (sin N+1) la ruta física
 * de cada espécimen recorriendo unit_tray_especimenes → unit_trays → cajas → ranuras_gabinete →
 * gabinetes. La caja puede no estar en una ranura (ranura_actual_id nulo): en ese caso la ranura y
 * el gabinete quedan en null pero la caja y el tray sí se devuelven.
 */
final class EloquentUbicacionEspecimenAdapter implements UbicacionEspecimenPort
{
    /**
     * @param  string[]  $especimenIds
     * @return array<string, array{cajaCodigo: ?string, trayNumero: ?int, ranuraNumero: ?int, gabineteCodigo: ?string}>
     */
    public function ubicacionesPorEspecimenes(array $especimenIds): array
    {
        if ($especimenIds === []) {
            return [];
        }

        $filas = DB::table('iot.unit_tray_especimenes as ute')
            ->join('iot.unit_trays as ut', 'ut.id', '=', 'ute.unit_tray_id')
            ->join('iot.cajas as c', 'c.id', '=', 'ut.caja_id')
            ->leftJoin('iot.ranuras_gabinete as rg', 'rg.id', '=', 'c.ranura_actual_id')
            ->leftJoin('iot.gabinetes as g', 'g.id', '=', 'rg.gabinete_id')
            ->whereIn('ute.especimen_id', $especimenIds)
            ->select([
                'ute.especimen_id',
                'c.codigo as caja_codigo',
                'ut.numero as tray_numero',
                'rg.numero_ranura as ranura_numero',
                'g.codigo as gabinete_codigo',
            ])
            ->get();

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(string) $fila->especimen_id] = [
                'cajaCodigo' => $fila->caja_codigo !== null ? (string) $fila->caja_codigo : null,
                'trayNumero' => $fila->tray_numero !== null ? (int) $fila->tray_numero : null,
                'ranuraNumero' => $fila->ranura_numero !== null ? (int) $fila->ranura_numero : null,
                'gabineteCodigo' => $fila->gabinete_codigo !== null ? (string) $fila->gabinete_codigo : null,
            ];
        }

        return $resultado;
    }
}
