<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

/**
 * Output DTO para el caso de uso de consultar una solicitud de préstamo.
 */
final readonly class ConsultarSolicitudPrestamoOutput
{
    /**
     * @param string $solicitudId ID de la solicitud.
     * @param string $numeroSolicitud Número de la solicitud.
     * @param string $investigadorId ID del investigador.
     * @param EstadoSolicitud $estado Estado de la solicitud.
     * @param DateTimeImmutable|null $enviadaEn Fecha de envío, si corresponde.
     */
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public string $investigadorId,
        public EstadoSolicitud $estado,
        public ?DateTimeImmutable $enviadaEn,
    ) {}

    /**
     * Crea una instancia de salida a partir de una entidad SolicitudPrestamo.
     *
     * @param SolicitudPrestamo $solicitud Entidad SolicitudPrestamo.
     * @return self
     */
    public static function fromEntity(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId: (string) $solicitud->id(),
            numeroSolicitud: (string) $solicitud->codigoPrestamo(),
            investigadorId: $solicitud->investigadorId(),
            estado: $solicitud->estado(),
            enviadaEn: $solicitud->enviadaEn(),
        );
    }
}
