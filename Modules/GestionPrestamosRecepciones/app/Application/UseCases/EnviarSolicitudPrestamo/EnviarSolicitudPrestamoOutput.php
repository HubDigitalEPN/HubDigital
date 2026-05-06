<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;

final readonly class EnviarSolicitudPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $numeroSolicitud,
        public EstadoSolicitud $estado,
        public ?DateTimeImmutable $enviadaEn,
    ) {}

    public static function fromPrimitives(SolicitudPrestamo $solicitud): self
    {
        return new self(
            solicitudId:     (string) $solicitud->id(),
            numeroSolicitud: (string) $solicitud->numeroSolicitud(),
            estado:          $solicitud->estado(),
            enviadaEn:       $solicitud->enviadaEn(),
        );
    }
}
