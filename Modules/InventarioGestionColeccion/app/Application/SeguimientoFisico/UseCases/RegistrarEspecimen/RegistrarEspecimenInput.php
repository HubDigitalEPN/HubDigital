<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarEspecimen;

final readonly class RegistrarEspecimenInput
{
    public function __construct(
        public string $codigoCatalogo,
        public string $taxonId,
        public string $localidad,
        public string $fechaColecta,
        public string $colector,
        public ?string $entidadDepositanteId = null,
        public ?string $occurrenceId = null,
        public ?string $catalogNumber = null,
        public ?string $oldCode = null,
        public ?string $cardexLiquidCollectionCode = null,
        public ?int $individualCount = null,
        public ?string $preparations = null,
        public ?string $disposition = null,
        public ?string $occurrenceStatus = null,
        public ?string $specimenNotes = null,
        public ?string $country = null,
        public ?string $stateProvince = null,
        public ?string $municipality = null,
        public ?string $localityName = null,
        public ?float $decimalLatitude = null,
        public ?float $decimalLongitude = null,
        public ?string $geodeticDatum = null,
        public ?float $elevationInMeters = null,
        public ?string $biome = null,
        public ?string $habitat = null,
        public array $identificadores = [],
    ) {}
}
