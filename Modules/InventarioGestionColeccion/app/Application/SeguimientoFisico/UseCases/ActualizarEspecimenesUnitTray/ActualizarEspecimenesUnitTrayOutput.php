<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

final readonly class ActualizarEspecimenesUnitTrayOutput
{
    private function __construct(
        public string $unitTrayId,
        public bool $tieneClasificacion,
        public ?string $subfamiliaAsignada,
        public ?string $generoAsignado,
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            unitTrayId: $data['unitTrayId'],
            tieneClasificacion: $data['tieneClasificacion'],
            subfamiliaAsignada: $data['subfamiliaAsignada'],
            generoAsignado: $data['generoAsignado'],
        );
    }
}
