<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AsociarPermisoAEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

final class AsociarPermisoAEspecimenHandler
{
    public function __construct(
        private readonly EspecimenPermisoRepositoryInterface $pivoteRepo,
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly PermisoRepositoryInterface $permisoRepo,
    ) {}

    public function handle(AsociarPermisoAEspecimenInput $input): AsociarPermisoAEspecimenOutput
    {
        $especimenId = EspecimenId::desde($input->especimenId);
        $especimen = $this->especimenRepo->buscarPorId($especimenId);
        if ($especimen === null) {
            throw new \DomainException("Espécimen no encontrado: '{$input->especimenId}'.");
        }

        $permisoId = PermisoId::desde($input->permisoId);
        $permiso = $this->permisoRepo->buscarPorId($permisoId);
        if ($permiso === null) {
            throw new \DomainException("Permiso no encontrado: '{$input->permisoId}'.");
        }

        $yaExistia = $this->pivoteRepo->existeAsociacion($especimenId, $permisoId);
        if (! $yaExistia) {
            $this->pivoteRepo->asociar($especimenId, $permisoId);
        }

        return new AsociarPermisoAEspecimenOutput(
            especimenId: (string) $especimenId,
            permisoId: (string) $permisoId,
            yaExistia: $yaExistia,
        );
    }
}
