<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

/**
 * Repositorio del pivote especimen_permiso (relación muchos-a-muchos entre
 * especímenes y permisos legales que los cubren — research/transport/export_import).
 *
 * El pivote NO es modelado dentro de la entidad Especimen para mantener la
 * entidad simple. Se accede vía este repo dedicado.
 */
interface EspecimenPermisoRepositoryInterface
{
    public function asociar(EspecimenId $especimenId, PermisoId $permisoId): void;

    public function desasociar(EspecimenId $especimenId, PermisoId $permisoId): void;

    public function existeAsociacion(EspecimenId $especimenId, PermisoId $permisoId): bool;

    /**
     * @return PermisoId[]
     */
    public function listarPermisosDe(EspecimenId $especimenId): array;

    /**
     * @return EspecimenId[]
     */
    public function listarEspecimenesDe(PermisoId $permisoId): array;
}
