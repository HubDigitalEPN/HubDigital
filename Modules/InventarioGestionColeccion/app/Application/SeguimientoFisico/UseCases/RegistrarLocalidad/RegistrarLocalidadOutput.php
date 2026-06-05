<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;

final readonly class RegistrarLocalidadOutput
{
    public function __construct(
        public LocalidadId $id,
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
