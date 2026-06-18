<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Prioridad de una solicitud en la cola de revisión curatorial. Una solicitud
 * clasificada como prioritaria se adelanta frente a las de prioridad normal.
 */
enum PrioridadSolicitud: string
{
    case Normal = 'Normal';
    case Prioritaria = 'Prioritaria';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
