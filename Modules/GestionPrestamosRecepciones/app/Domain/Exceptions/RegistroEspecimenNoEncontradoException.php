<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

/**
 * Excepción de dominio lanzada cuando no se encuentra un registro de espécimen dentro
 * de la matriz. Construir con {@see conId()}.
 */
final class RegistroEspecimenNoEncontradoException extends \DomainException
{
    public static function conId(string $registroId): self
    {
        return new self(
            sprintf('No se encontró un registro de espécimen con ID "%s" en la matriz', $registroId)
        );
    }
}
