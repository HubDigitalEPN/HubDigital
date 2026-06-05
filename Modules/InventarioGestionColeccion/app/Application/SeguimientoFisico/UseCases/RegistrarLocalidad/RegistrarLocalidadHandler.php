<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

final class RegistrarLocalidadHandler
{
    public function __construct(
        private readonly LocalidadRepositoryInterface $localidadRepo,
    ) {}

    public function handle(RegistrarLocalidadInput $input): RegistrarLocalidadOutput
    {
        $rangoVO = RangoLocalidad::desdeValorFlexible($input->rango);

        if ($rangoVO === null) {
            throw new \InvalidArgumentException(
                "Rango de localidad inválido: '{$input->rango}'. Valores válidos: ".
                implode(', ', RangoLocalidad::valoresAceptados())
            );
        }

        if ($input->padreId !== null) {
            $padre = $this->localidadRepo->buscarPorId(LocalidadId::desde($input->padreId));

            if ($padre === null) {
                throw new \DomainException("Localidad padre no encontrada: '{$input->padreId}'.");
            }

            if (! $padre->rango()->puedeSerPadreDe($rangoVO)) {
                throw new \DomainException(
                    "El rango '{$padre->rango()->value}' no puede ser padre de '{$rangoVO->value}'."
                );
            }
        }

        $existente = $this->localidadRepo->buscarPorNombreYRango($input->nombreCanonico, $rangoVO);

        if ($existente !== null) {
            throw new \DomainException(
                "Ya existe una localidad con nombre '{$input->nombreCanonico}' y rango '{$rangoVO->value}'."
            );
        }

        $localidad = Localidad::crear(
            id: $this->localidadRepo->nextIdentity(),
            nombreCanonico: $input->nombreCanonico,
            rango: $rangoVO->value,
            padreId: $input->padreId,
            latitud: $input->latitud,
            longitud: $input->longitud,
            geodeticDatum: $input->geodeticDatum,
            coordinateUncertaintyM: $input->coordinateUncertaintyM,
            latLonVerbatim: $input->latLonVerbatim,
            country: $input->country,
            stateProvince: $input->stateProvince,
            municipality: $input->municipality,
        );

        $this->localidadRepo->guardar($localidad);

        return new RegistrarLocalidadOutput(
            id: $localidad->id(),
            nombreCanonico: $localidad->nombreCanonico(),
            rango: $localidad->rango()->value,
            padreId: $localidad->padreId() !== null ? (string) $localidad->padreId() : null,
            latitud: $localidad->coordenada()?->latitud,
            longitud: $localidad->coordenada()?->longitud,
            geodeticDatum: $localidad->geodeticDatum(),
            coordinateUncertaintyM: $localidad->coordinateUncertaintyM(),
            latLonVerbatim: $localidad->latLonVerbatim(),
            country: $localidad->country(),
            stateProvince: $localidad->stateProvince(),
            municipality: $localidad->municipality(),
        );
    }
}
