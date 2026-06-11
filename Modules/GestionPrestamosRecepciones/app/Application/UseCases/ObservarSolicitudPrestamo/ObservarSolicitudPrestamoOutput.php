<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;

/**
 * Datos de salida tras observar una solicitud de préstamo.
 */
final readonly class ObservarSolicitudPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public string $estadoSolicitud,
        public ?string $comentarioCurador,
    ) {}

    /**
     * @param SolicitudPrestamo $solicitud
     * @return self
     */
    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId: (string) $solicitud->id(),
            numeroSolicitud: (string) $solicitud->numeroSolicitud(),
            estadoSolicitud: $solicitud->estado()->value,
            comentarioCurador: $solicitud->comentarioCurador(),
        );
    }
}
