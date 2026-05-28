<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion;

final readonly class VerificarTiemposExtraccionOutput
{
    public function __construct(
        public string $cajaId,
        public string $estadoCaja,
        public bool $alertaGenerada,
        public bool $notificacionPreventiva,
        public ?string $tipoAlerta = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromPrimitives(array $data): self
    {
        return new self(
            cajaId: $data['cajaId'],
            estadoCaja: $data['estadoCaja'],
            alertaGenerada: $data['alertaGenerada'],
            notificacionPreventiva: $data['notificacionPreventiva'],
            tipoAlerta: $data['tipoAlerta'] ?? null,
        );
    }
}
