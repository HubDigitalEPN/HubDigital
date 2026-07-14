<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga;

final readonly class SolicitarProrrogaPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public string $solicitudProrrogaId,
        public string $estado,
    ) {}

    public static function from(Prestamo $prestamo, SolicitudProrroga $solicitud): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            solicitudProrrogaId: (string) $solicitud->id(),
            estado: $prestamo->estado()->value,
        );
    }
}
