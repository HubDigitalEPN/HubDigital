<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

/**
 * Excepción de dominio lanzada cuando un investigador intenta operar sobre un acta
 * que no le pertenece. Construir con {@see conActaId()}.
 */
final class ActaNoPerteneceAlInvestigador extends DomainException
{
    public static function conActaId(ActaPrestamoId $actaId): self
    {
        return new self("El acta '{$actaId}' no pertenece al investigador indicado.");
    }
}
