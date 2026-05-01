<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja;

final readonly class RegistrarRetiroCajaOutput
{
    public function __construct(
        public string $cajaId,
        public string $estadoCaja,
        public bool $alertaGenerada,
        public bool $notificacionEnviada,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromPrimitives(array $data): self
    {
        return new self(
            cajaId: $data['cajaId'],
            estadoCaja: $data['estadoCaja'],
            alertaGenerada: $data['alertaGenerada'],
            notificacionEnviada: $data['notificacionEnviada'],
        );
    }
}
