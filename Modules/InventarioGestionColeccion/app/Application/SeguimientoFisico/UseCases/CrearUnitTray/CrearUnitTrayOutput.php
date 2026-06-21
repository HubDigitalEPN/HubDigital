<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray;

/**
 * DTO de salida con los datos del unit tray creado y su clasificación dominante resultante.
 */
final readonly class CrearUnitTrayOutput
{
    private function __construct(
        public string $unitTrayId,
        public string $cajaId,
        public int $numero,
        public bool $tieneClasificacion,
        public ?string $subfamiliaAsignada,
        public ?string $generoAsignado,
    ) {}

    public static function fromPrimitives(array $data): self
    {
        return new self(
            unitTrayId: $data['unitTrayId'],
            cajaId: $data['cajaId'],
            numero: $data['numero'],
            tieneClasificacion: $data['tieneClasificacion'],
            subfamiliaAsignada: $data['subfamiliaAsignada'],
            generoAsignado: $data['generoAsignado'],
        );
    }
}
