<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

/**
 * Datos de salida tras habilitar el envío internacional.
 */
final readonly class HabilitarEnvioInternacionalOutput
{
    public function __construct(
        public string $prestamoId,
        public string $estadoPrestamo,
    ) {}

    /**
     * @param Prestamo $prestamo
     * @return self
     */
    public static function fromPrestamo(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estadoPrestamo: $prestamo->estado()->value,
        );
    }
}
