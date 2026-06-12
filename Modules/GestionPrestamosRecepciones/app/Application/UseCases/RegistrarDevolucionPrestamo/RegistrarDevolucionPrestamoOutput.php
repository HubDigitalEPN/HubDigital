<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;

/**
 * Datos de salida del caso de uso de registro de devolución de préstamo.
 */
final readonly class RegistrarDevolucionPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public EstadoPrestamo $estadoPrestamo,
    ) {}

    public static function fromPrestamo(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estadoPrestamo: $prestamo->estado(),
        );
    }
}
