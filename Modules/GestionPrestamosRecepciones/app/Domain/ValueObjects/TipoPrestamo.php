<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Tipo de préstamo de especímenes: temporal (con devolución) o permanente.
 */
enum TipoPrestamo: string
{
    case Temporal = 'temporal';
    case Permanente = 'permanente';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
