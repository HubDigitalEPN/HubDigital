<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Exceptions;

/** Lanzada al intentar iniciar la recepción de un lote cuya solicitud no está en el estado requerido. */
final class RecepcionNoIniciableException extends ApplicationException
{
    public static function solicitudNoAprobada(string $solicitudId): self
    {
        return new self(
            sprintf('No se puede iniciar la recepción: la solicitud "%s" no está aprobada documentalmente', $solicitudId)
        );
    }
}
