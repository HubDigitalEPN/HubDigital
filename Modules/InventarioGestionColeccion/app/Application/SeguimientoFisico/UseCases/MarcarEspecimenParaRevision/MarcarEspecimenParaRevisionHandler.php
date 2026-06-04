<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenParaRevision;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class MarcarEspecimenParaRevisionHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(MarcarEspecimenParaRevisionInput $input): MarcarEspecimenParaRevisionOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));
        if ($especimen === null) {
            throw new \DomainException("Espécimen no encontrado: '{$input->especimenId}'.");
        }

        $especimen->marcarParaRevision($input->motivo);

        $this->especimenRepo->guardar($especimen);

        return new MarcarEspecimenParaRevisionOutput(
            especimenId: (string) $especimen->id(),
            estadoRevision: $especimen->estadoRevision()->value,
            motivoRevision: $especimen->motivoRevision() ?? '',
        );
    }
}
