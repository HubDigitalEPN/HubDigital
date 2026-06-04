<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad;

final readonly class ActualizarLocalidadInput
{
    public function __construct(
        public string $localidadId,
        public string $nombreCanonico,
        public string $rango,
        public ?string $padreId = null,
        public ?float $latitud = null,
        public ?float $longitud = null,
        public ?string $geodeticDatum = null,
        public ?float $coordinateUncertaintyM = null,
        public ?string $latLonVerbatim = null,
        public ?string $country = null,
        public ?string $stateProvince = null,
        public ?string $municipality = null,
    ) {}
}
