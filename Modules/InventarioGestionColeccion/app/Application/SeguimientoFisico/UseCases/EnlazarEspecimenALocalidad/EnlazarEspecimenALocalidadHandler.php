<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenALocalidad;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;

final class EnlazarEspecimenALocalidadHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(EnlazarEspecimenALocalidadInput $input): EnlazarEspecimenALocalidadOutput
    {
        $especimen = $this->especimenRepo->buscarPorId(EspecimenId::desde($input->especimenId));
        if ($especimen === null) {
            throw new \DomainException("Espécimen no encontrado: '{$input->especimenId}'.");
        }

        $localidadId = LocalidadId::desde($input->localidadId);
        $localidad = $this->localidadRepo->buscarPorId($localidadId);
        if ($localidad === null) {
            throw new \DomainException("Localidad no encontrada: '{$input->localidadId}'.");
        }

        $especimen->enlazarLocalidad($localidadId);

        $this->especimenRepo->guardar($especimen);

        return new EnlazarEspecimenALocalidadOutput(
            especimenId: (string) $especimen->id(),
            localidadId: $especimen->localidadId() ?? '',
            localidadVerbatim: $especimen->localidadVerbatim(),
        );
    }
}
