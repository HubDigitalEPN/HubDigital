<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades;

final readonly class ListarLocalidadesItemOutput
{
    public function __construct(
        public string $id,
        public string $nombreCanonico,
        public string $rango,
        public ?string $padreId,
        public ?string $padreNombre,
        public ?float $latitud,
        public ?float $longitud,
        public ?string $country,
        public ?string $stateProvince,
        public ?string $municipality,
    ) {}
}
