<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimen;

final readonly class ActualizarEspecimenOutput
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public ?string $entidadDepositanteId,
        public ?string $country = null,
        public ?string $stateProvince = null,
        public ?string $municipality = null,
        public ?string $localityName = null,
        public ?float $decimalLatitude = null,
        public ?float $decimalLongitude = null,
        public ?string $geodeticDatum = null,
        public ?float $elevationMinM = null,
        public ?string $biome = null,
        public ?string $habitat = null,
        public ?string $preparations = null,
        public ?string $disposition = null,
        public ?string $occurrenceStatus = null,
        public ?string $specimenNotes = null,
    ) {}
}
