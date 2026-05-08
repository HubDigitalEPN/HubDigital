<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

enum EstadoSolicitud: string
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Observada = 'observada';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public function equals(self $other): bool
    {
        return $this === $other;
    }

    /** Retorna los estados desde los cuales se puede enviar la solicitud. */
    public function puedeEnviar(): bool
    {
        return $this === self::Borrador || $this === self::Observada;
    }

    /** Retorna los estados desde los cuales el curador puede resolver la solicitud. */
    public function puedeResolver(): bool
    {
        return $this === self::Enviada;
    }
}
