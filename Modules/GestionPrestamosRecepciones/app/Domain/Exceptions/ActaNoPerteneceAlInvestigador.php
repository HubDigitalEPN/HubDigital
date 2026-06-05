<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;

final class ActaNoPerteneceAlInvestigador extends DomainException
{
    public static function conActaId(ActaPrestamoId $actaId): self
    {
        return new self("El acta '{$actaId}' no pertenece al investigador indicado.");
    }
}
