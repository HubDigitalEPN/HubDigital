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
            country: $input->country,
            stateProvince: $input->stateProvince,
            municipality: $input->municipality,
            localityName: $input->localityName,
            decimalLatitude: $input->decimalLatitude,
            decimalLongitude: $input->decimalLongitude,
            geodeticDatum: $input->geodeticDatum,
            elevationMinM: $input->elevationMinM,
            biome: $input->biome,
            habitat: $input->habitat,
            preparations: $input->preparations,
            disposition: $input->disposition,
            occurrenceStatus: $input->occurrenceStatus,
            specimenNotes: $input->specimenNotes,
        );

        $this->especimenRepo->guardar($especimen);

        return new ActualizarEspecimenOutput(
            especimenId: (string) $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            entidadDepositanteId: $especimen->entidadDepositanteId(),
            country: $especimen->country(),
            stateProvince: $especimen->stateProvince(),
            municipality: $especimen->municipality(),
            localityName: $especimen->localityName(),
            decimalLatitude: $especimen->decimalLatitude(),
            decimalLongitude: $especimen->decimalLongitude(),
            geodeticDatum: $especimen->geodeticDatum(),
            elevationMinM: $especimen->elevationMinM(),
            biome: $especimen->biome(),
            habitat: $especimen->habitat(),
            preparations: $especimen->preparations(),
            disposition: $especimen->disposition(),
            occurrenceStatus: $especimen->occurrenceStatus(),
            specimenNotes: $especimen->specimenNotes(),
        );
    }
}
