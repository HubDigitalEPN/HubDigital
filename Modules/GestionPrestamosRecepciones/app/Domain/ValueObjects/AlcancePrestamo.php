<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Alcance geográfico de un préstamo: nacional o internacional.
 *
 * El alcance internacional dispara requisitos adicionales (documento de exportación
 * del Ministerio del Ambiente) y un estado inicial distinto del préstamo.
 */
enum AlcancePrestamo: string
{
    case Nacional = 'nacional';
    case Internacional = 'internacional';

    public function equals(self $other): bool
    {
        return $this === $other;
    }

    /** Indica si el alcance es internacional (requiere documentación del MAE). */
    public function esInternacional(): bool
    {
        return $this === self::Internacional;
    }
}
