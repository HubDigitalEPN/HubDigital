<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Excepción de dominio lanzada al intentar una transición de estado no permitida en
 * un agregado (préstamo, acta, solicitud). Construir con {@see para()}.
 */
final class TransicionDeEstadoInvalidaException extends DomainException
{
    public static function para(string $agregado, string $estadoActual, string $accion): self
    {
        return new self(
            "No se puede ejecutar '{$accion}' sobre {$agregado} en estado '{$estadoActual}'."
        );
    }
}
