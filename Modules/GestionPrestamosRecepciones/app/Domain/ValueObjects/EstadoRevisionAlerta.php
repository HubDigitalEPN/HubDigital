<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

use Modules\GestionPrestamosRecepciones\Domain\Entities\AlertaSolicitud;

/**
 * Estado de revisión de una {@see AlertaSolicitud}
 * por parte del curador: pendiente de revisión, con justificación aceptada o
 * con justificación rechazada.
 */
enum EstadoRevisionAlerta: string
{
    case PendienteRevision = 'Pendiente de Revisión';
    case Aceptada = 'Aceptada';
    case Rechazada = 'Rechazada';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
