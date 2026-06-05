<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarRevisionEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class ConfirmarRevisionEspecimenHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $repo,
    ) {}

    public function handle(ConfirmarRevisionEspecimenInput $input): ConfirmarRevisionEspecimenOutput
    {
        $especimen = $this->repo->buscarPorId(EspecimenId::desde($input->especimenId));
        if ($especimen === null) {
            throw new \DomainException("Espécimen '{$input->especimenId}' no encontrado.");
        }

        $especimen->confirmarRevision();
        $this->repo->guardar($especimen);

        return new ConfirmarRevisionEspecimenOutput(
            especimenId: $input->especimenId,
            estadoRevision: $especimen->estadoRevision()->value,
        );
    }
}
