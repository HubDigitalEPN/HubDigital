<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

final class CamposDwCFaltantesException extends \DomainException
{
    /**
     * @param  string[]  $campos
     */
    public static function porCamposFaltantes(array $campos): self
    {
        return new self(
            sprintf(
                'La matriz no contiene los siguientes campos DwC requeridos por la colección: %s',
                implode(', ', $campos)
            )
        );
    }
}
