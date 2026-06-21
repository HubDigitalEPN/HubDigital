<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

/**
 * Resuelve la ubicación física legible de un conjunto de especímenes en una sola consulta,
 * recorriendo la jerarquía Especimen → UnitTray → Caja → Ranura → Gabinete.
 *
 * Sirve para distinguir, en el buscador de asignación, cuál de varios especímenes con el mismo
 * nombre científico (y solo distinto código de catálogo) es cuál: muestra dónde está cada uno.
 * Solo aparecen los especímenes que están asignados a un unit tray.
 */
interface UbicacionEspecimenPort
{
    /**
     * @param  string[]  $especimenIds
     * @return array<string, array{cajaCodigo: ?string, trayNumero: ?int, ranuraNumero: ?int, gabineteCodigo: ?string}>
     *                                                                                                                  especimenId => ubicación (solo los asignados a un unit tray)
     */
    public function ubicacionesPorEspecimenes(array $especimenIds): array;
}
