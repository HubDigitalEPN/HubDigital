<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;

final readonly class RechazarSolicitudPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $estadoSolicitud,
        public ?string $comentarioCurador,
    ) {}

    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId: (string) $solicitud->id(),
            estadoSolicitud: $solicitud->estado()->value,
            comentarioCurador: $solicitud->comentarioCurador(),
        );
    }
}
