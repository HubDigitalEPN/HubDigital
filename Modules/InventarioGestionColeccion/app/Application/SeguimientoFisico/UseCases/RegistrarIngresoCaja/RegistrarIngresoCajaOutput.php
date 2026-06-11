<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja;

/**
 * DTO de salida con el resultado del ingreso: caja, ranura, estado, ubicación abierta y si generó alerta.
 */
final readonly class RegistrarIngresoCajaOutput
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
        public string $estadoCaja,
        public string $ubicacionCajaId,
        public bool $alertaGenerada,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromPrimitives(array $data): self
    {
        return new self(
            cajaId: $data['cajaId'],
            ranuraId: $data['ranuraId'],
            estadoCaja: $data['estadoCaja'],
            ubicacionCajaId: $data['ubicacionCajaId'],
            alertaGenerada: $data['alertaGenerada'],
        );
    }
}
