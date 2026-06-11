<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Excepción de dominio lanzada al intentar enviar una solicitud de préstamo a la que
 * le falta información requerida. Construir con {@see paraEnvio()}.
 */
final class SolicitudPrestamoIncompletaException extends DomainException
{
    public static function paraEnvio(): self
    {
        return new self(
            'La solicitud no puede enviarse porque le falta información requerida: '.
            'título, institución, línea de investigación, propósito, duración mayor a 0 y al menos un ítem.'
        );
    }
}
