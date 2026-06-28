<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

/**
 * Output DTO para el caso de uso de enviar una solicitud de préstamo.
 */
final readonly class EnviarSolicitudPrestamoOutput
{
    /**
     * @param string $solicitudId ID de la solicitud.
     * @param string $numeroSolicitud Número de la solicitud.
     * @param EstadoSolicitud $estado Estado de la solicitud tras el envío.
     * @param DateTimeImmutable|null $enviadaEn Fecha de envío.
     */
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public EstadoSolicitud $estado,
        public ?DateTimeImmutable $enviadaEn,
    ) {}

    /**
     * Crea una instancia a partir de la entidad SolicitudPrestamo.
     *
     * @param SolicitudPrestamo $solicitud Entidad SolicitudPrestamo.
     * @return self
     */
    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId:     (string) $solicitud->id(),
            numeroSolicitud: (string) $solicitud->codigoPrestamo(),
            estado:          $solicitud->estado(),
            enviadaEn:       $solicitud->enviadaEn(),
        );
    }
}
