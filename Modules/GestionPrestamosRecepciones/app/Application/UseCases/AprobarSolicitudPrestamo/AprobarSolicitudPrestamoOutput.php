<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;

final readonly class AprobarSolicitudPrestamoOutput
{
    public function __construct(
        public string $solicitudId,
        public string $estadoSolicitud,
        public string $actaId,
        public string $numeroPrestamo,
        public string $tipoPrestamo,
        public ?string $condicionesGenerales,
    ) {}

    public static function from(SolicitudPrestamo $solicitud, ActaPrestamo $acta): self
    {
        return new self(
            solicitudId: (string) $solicitud->id(),
            estadoSolicitud: $solicitud->estado()->value,
            actaId: (string) $acta->id(),
            numeroPrestamo: (string) $acta->numeroPrestamo(),
            tipoPrestamo: $acta->tipoPrestamo()->value,
            condicionesGenerales: $acta->condicionesGenerales(),
        );
    }
}
