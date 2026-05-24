<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

final readonly class ConsultarSolicitudPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public string $investigadorId,
        public EstadoSolicitud $estado,
        public ?DateTimeImmutable $enviadaEn,
    ) {}

    public static function fromEntity(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId: (string) $solicitud->id(),
            numeroSolicitud: (string) $solicitud->numeroSolicitud(),
            investigadorId: $solicitud->investigadorId(),
            estado: $solicitud->estado(),
            enviadaEn: $solicitud->enviadaEn(),
        );
    }
}
