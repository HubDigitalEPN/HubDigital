<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

final class PrestamoNoEncontradoException extends DomainException
{
    public static function conId(PrestamoId $id): self
    {
        return new self("No se encontró el préstamo con id '{$id}'.");
    }
}
