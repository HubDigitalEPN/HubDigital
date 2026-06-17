<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

/**
 * Excepción de dominio lanzada al transicionar una solicitud de depósito entre
 * estados incompatibles. Construir con {@see de()}.
 */
final class TransicionEstadoInvalida extends \DomainException
{
    public static function de(string $estadoActual, string $estadoSolicitado): self
    {
        return new self(
            sprintf('No es válido transicionar de estado "%s" a "%s"', $estadoActual, $estadoSolicitado)
        );
    }
}
