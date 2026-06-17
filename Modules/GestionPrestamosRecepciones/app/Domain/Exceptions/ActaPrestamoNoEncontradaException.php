<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Excepción de dominio lanzada cuando no se encuentra un acta de préstamo, ya sea por
 * su identificador ({@see conId()}) o por la solicitud asociada ({@see paraSolicitud()}).
 */
final class ActaPrestamoNoEncontradaException extends DomainException
{
    public static function conId(ActaPrestamoId $id): self
    {
        return new self("No se encontró el acta de préstamo con id '{$id}'.");
    }

    public static function paraSolicitud(SolicitudPrestamoId $solicitudId): self
    {
        return new self("No se encontró un acta de préstamo para la solicitud '{$solicitudId}'.");
    }
}
