<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

final class TransicionDeEstadoInvalidaException extends DomainException
{
    public static function para(string $agregado, string $estadoActual, string $accion): self
    {
        return new self(
            "No se puede ejecutar '{$accion}' sobre {$agregado} en estado '{$estadoActual}'."
        );
    }
}
