<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;

interface PermisoRepositoryInterface
{
    public function nextIdentity(): PermisoId;

    public function guardar(Permiso $permiso): void;

    public function buscarPorId(PermisoId $id): ?Permiso;

    /** @return Permiso[] */
    public function buscarPorTipo(TipoPermiso $tipo): array;

    /** @return Permiso[] */
    public function buscarTodos(): array;

    public function contarTodos(): int;
}
