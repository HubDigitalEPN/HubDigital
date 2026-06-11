<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Excepción de dominio lanzada cuando no se encuentra una solicitud de préstamo por
 * su identificador. Construir con {@see conId()}.
 */
final class SolicitudPrestamoNoEncontradaException extends DomainException
{
    public static function conId(SolicitudPrestamoId $id): self
    {
        return new self("No se encontró la solicitud de préstamo con id '{$id}'.");
    }
}
