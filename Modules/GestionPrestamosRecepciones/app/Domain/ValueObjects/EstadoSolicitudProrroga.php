<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estados del ciclo de vida de una solicitud de prórroga
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudProrroga}).
 *
 * Flujo: solicitada → aprobada o rechazada por el curador.
 */
enum EstadoSolicitudProrroga: string
{
    case Solicitada = 'solicitada';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
