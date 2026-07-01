<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

final readonly class RechazarProrrogaPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public string $fechaFin,
        public string $estado,
    ) {}

    public static function from(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            fechaFin: $prestamo->fechaFin()->format('Y-m-d'),
            estado: $prestamo->estado()->value,
        );
    }
}
