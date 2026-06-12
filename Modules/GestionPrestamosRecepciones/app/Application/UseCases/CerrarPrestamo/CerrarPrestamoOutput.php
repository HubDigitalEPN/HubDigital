<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CondicionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoPrestamo;

/**
 * Datos de salida del caso de uso de cierre de préstamo.
 */
final readonly class CerrarPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public EstadoPrestamo $estadoPrestamo,
        public CondicionEspecimen $condicionEspecimen,
    ) {}

    public static function fromPrestamo(Prestamo $prestamo, CondicionEspecimen $condicionEspecimen): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estadoPrestamo: $prestamo->estado(),
            condicionEspecimen: $condicionEspecimen,
        );
    }
}
