<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega;

use Modules\GestionPrestamosRecepciones\Domain\Entities\Prestamo;

/**
 * Datos de salida del caso de uso de aprobación de verificación de entrega.
 */
final readonly class AprobarVerificacionEntregaOutput
{
    public function __construct(
        public string $prestamoId,
        public string $estado,
    ) {}

    /**
     * Crea un objeto de salida a partir de la entidad Prestamo.
     */
    public static function fromPrestamo(Prestamo $prestamo): self
    {
        return new self(
            prestamoId: (string) $prestamo->id(),
            estado: $prestamo->estado()->value,
        );
    }
}
