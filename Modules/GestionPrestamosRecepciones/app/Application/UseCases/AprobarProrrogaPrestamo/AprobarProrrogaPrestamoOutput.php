<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

final readonly class AprobarProrrogaPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public string $nuevaFechaFin,
        public string $estado,
    ) {}

    public static function from(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            nuevaFechaFin: $prestamo->fechaFin()->format('Y-m-d'),
            estado: $prestamo->estado()->value,
        );
    }
}
