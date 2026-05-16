<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

final class SolicitudPrestamoNoEncontradaException extends DomainException
{
    public static function conId(SolicitudPrestamoId $id): self
    {
        return new self("No se encontró la solicitud de préstamo con id '{$id}'.");
    }
}
