<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Value object inmutable que representa una observación (novedad) registrada sobre
 * un ítem de préstamo durante la verificación de entrega.
 */
final readonly class ObservacionEspecimen
{
    public function __construct(
        public string $itemPrestamoId,
        public string $descripcion,
    ) {}
}
