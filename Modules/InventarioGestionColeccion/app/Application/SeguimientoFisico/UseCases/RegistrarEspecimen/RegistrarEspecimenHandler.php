<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\CodigoCatalogoDuplicadoException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

final class RegistrarEspecimenHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
        private readonly TaxonRepositoryInterface $taxonRepo,
        private readonly EntidadDepositanteRepositoryInterface $entidadRepo,
    ) {}

    public function handle(RegistrarEspecimenInput $input): RegistrarEspecimenOutput
    {
        $taxon = $this->taxonRepo->buscarPorId(TaxonId::desde($input->taxonId));

        if ($taxon === null) {
            throw new \DomainException("Taxón no encontrado: '{$input->taxonId}'.");
        }

        if ($input->entidadDepositanteId !== null) {
            $entidad = $this->entidadRepo->buscarPorId(EntidadDepositanteId::desde($input->entidadDepositanteId));

            if ($entidad === null) {
                throw new \DomainException("Entidad depositante no encontrada: '{$input->entidadDepositanteId}'.");
            }
        }

        $existente = $this->especimenRepo->buscarPorCodigoCatalogo($input->codigoCatalogo);

        if ($existente !== null) {
            throw new CodigoCatalogoDuplicadoException($input->codigoCatalogo);
        }

        $especimen = Especimen::crear(
            id: $this->especimenRepo->nextIdentity(),
            codigoCatalogo: $input->codigoCatalogo,
            taxonId: $input->taxonId,
            localidad: $input->localidad,
            fechaColecta: $input->fechaColecta,
            colector: $input->colector,
            entidadDepositanteId: $input->entidadDepositanteId,
            occurrenceId: $input->occurrenceId,
            catalogNumber: $input->catalogNumber,
            oldCode: $input->oldCode,
            cardexLiquidCollectionCode: $input->cardexLiquidCollectionCode,
            individualCount: $input->individualCount,
            preparations: $input->preparations,
            disposition: $input->disposition,
            occurrenceStatus: $input->occurrenceStatus,
            specimenNotes: $input->specimenNotes,
            country: $input->country,
            stateProvince: $input->stateProvince,
            municipality: $input->municipality,
            localityName: $input->localityName,
            decimalLatitude: $input->decimalLatitude,
            decimalLongitude: $input->decimalLongitude,
            geodeticDatum: $input->geodeticDatum,
            elevationInMeters: $input->elevationInMeters,
            biome: $input->biome,
            habitat: $input->habitat,
            identificadores: $input->identificadores,
        );

        $this->especimenRepo->guardar($especimen);

        return new RegistrarEspecimenOutput(
            id: $especimen->id(),
            codigoCatalogo: $especimen->codigoCatalogo(),
            taxonId: $especimen->taxonId(),
            localidad: $especimen->localidad(),
            fechaColecta: $especimen->fechaColecta(),
            colector: $especimen->colector(),
            estado: $especimen->estado()->value,
            entidadDepositanteId: $especimen->entidadDepositanteId(),
            occurrenceId: $especimen->occurrenceId(),
            catalogNumber: $especimen->catalogNumber(),
            oldCode: $especimen->oldCode(),
            cardexLiquidCollectionCode: $especimen->cardexLiquidCollectionCode(),
            individualCount: $especimen->individualCount(),
            preparations: $especimen->preparations(),
            disposition: $especimen->disposition(),
            occurrenceStatus: $especimen->occurrenceStatus(),
            specimenNotes: $especimen->specimenNotes(),
            country: $especimen->country(),
            stateProvince: $especimen->stateProvince(),
            municipality: $especimen->municipality(),
            localityName: $especimen->localityName(),
            decimalLatitude: $especimen->decimalLatitude(),
            decimalLongitude: $especimen->decimalLongitude(),
            geodeticDatum: $especimen->geodeticDatum(),
            elevationInMeters: $especimen->elevationInMeters(),
            biome: $especimen->biome(),
            habitat: $especimen->habitat(),
            identificadores: array_map(fn ($i) => $i->toArray(), $especimen->identificadores()),
        );
    }
}
