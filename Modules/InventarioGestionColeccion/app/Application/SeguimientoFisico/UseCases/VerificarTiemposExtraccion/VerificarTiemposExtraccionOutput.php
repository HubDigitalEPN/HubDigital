<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VerificarTiemposExtraccion;

/**
 * DTO de salida con el resultado de la verificación: estado de la caja, si generó alerta o
 * notificación preventiva y el tipo de alerta cuando aplica.
 */
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
