<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Persiste la membresía Especimen↔UnitTray dentro del componente de Seguimiento Físico,
 * sin modificar la entidad ni la tabla de especímenes (que pertenecen a otra capacidad).
 * El espécimen se referencia solo por su identificador (UUID).
 */
interface UnitTrayEspecimenRepository
{
    /**
     * Reemplaza el conjunto de especímenes asignados a un unit tray.
     * Un espécimen previamente asignado a otro tray se reubica a este.
     *
     * @param  string[]  $especimenIds
     */
    public function sincronizar(UnitTrayId $unitTrayId, array $especimenIds): void;

    /**
     * @return string[] UUIDs de los especímenes asignados al unit tray
     */
    public function especimenIdsPorUnitTray(UnitTrayId $unitTrayId): array;

    public function unitTrayDeEspecimen(string $especimenId): ?UnitTrayId;

    /**
     * Resuelve en una sola consulta el unit tray de cada espécimen del conjunto,
     * evitando el N+1 al listar especímenes asignables.
     *
     * @param  string[]  $especimenIds
     * @return array<string, string> especimenId => unitTrayId (solo los asignados)
     */
    public function unitTraysDeEspecimenes(array $especimenIds): array;

    public function eliminarPorUnitTray(UnitTrayId $unitTrayId): void;
}
