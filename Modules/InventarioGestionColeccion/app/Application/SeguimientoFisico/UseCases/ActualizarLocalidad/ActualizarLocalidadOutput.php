<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad;

final readonly class ActualizarLocalidadOutput
{
    public function __construct(
        public string $id,
        public string $nombreCanonico,
        public string $rango,
        public ?string $padreId,
        public ?float $latitud,
        public ?float $longitud,
        public ?string $geodeticDatum,
        public ?float $coordinateUncertaintyM,
        public ?string $latLonVerbatim,
        public ?string $country,
        public ?string $stateProvince,
        public ?string $municipality,
    ) {}
}
