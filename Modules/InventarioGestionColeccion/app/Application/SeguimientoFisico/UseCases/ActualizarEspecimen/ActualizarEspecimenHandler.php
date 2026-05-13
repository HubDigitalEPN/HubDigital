<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class ActualizarEspecimenHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(ActualizarEspecimenInput $input): ActualizarEspecimenOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));

        if ($especimen === null) {
            throw new \DomainException("Especímen '{$input->especimenId}' no encontrado.");
        }

        if ($input->entidadDepositanteId !== null) {
            $entidad = $this->entidadRepo->buscarPorId(EntidadDepositanteId::desde($input->entidadDepositanteId));

            if ($entidad === null) {
                throw new \DomainException("Entidad depositante '{$input->entidadDepositanteId}' no encontrada.");
            }
        }

        $especimen->actualizar(
            localidad: $input->localidad,
            fechaColecta: $input->fechaColecta,
            colector: $input->colector,
            entidadDepositanteId: $input->entidadDepositanteId,
        );

        $this->especimenRepo->guardar($especimen);

        return new ActualizarEspecimenOutput(
            especimenId: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            entidadDepositanteId: $especimen->entidadDepositanteId(),
        );
    }
}
