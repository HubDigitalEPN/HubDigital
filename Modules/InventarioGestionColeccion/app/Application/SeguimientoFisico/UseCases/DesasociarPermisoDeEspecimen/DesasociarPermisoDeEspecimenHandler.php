<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesasociarPermisoDeEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

final class DesasociarPermisoDeEspecimenHandler
{
    public function __construct(
        private readonly EspecimenPermisoRepositoryInterface $repo,
    ) {}

    public function handle(DesasociarPermisoDeEspecimenInput $input): DesasociarPermisoDeEspecimenOutput
    {
        $this->repo->desasociar(
            EspecimenId::desde($input->especimenId),
            PermisoId::desde($input->permisoId),
        );

        return new DesasociarPermisoDeEspecimenOutput(
            especimenId: $input->especimenId,
            permisoId: $input->permisoId,
        );
    }
}
