<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

/**
 * Datos de salida tras iniciar un préstamo.
 */
final readonly class IniciarPrestamoOutput
{
    public function __construct(
        public string $prestamoId,
        public string $estado,
    ) {}

    /**
     * @param Prestamo $prestamo
     * @return self
     */
    public static function fromPrestamo(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estado: $prestamo->estado()->value,
        );
    }
}
