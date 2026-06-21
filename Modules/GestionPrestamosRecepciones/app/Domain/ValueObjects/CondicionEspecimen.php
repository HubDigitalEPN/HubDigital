<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Condición del espécimen tras la verificación de devolución de un préstamo.
 * Determinada por el curador al inspeccionar el material recibido.
 */
enum CondicionEspecimen: string
{
    case Apto = 'apto';
    case NoApto = 'no_apto';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
