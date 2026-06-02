<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray;

final readonly class EliminarUnitTrayOutput
{
    private function __construct(
        public string $unitTrayId,
        public string $cajaId,
        public bool $cajaTieneClasificacion,
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            unitTrayId: $data['unitTrayId'],
            cajaId: $data['cajaId'],
            cajaTieneClasificacion: $data['cajaTieneClasificacion'],
        );
    }
}
