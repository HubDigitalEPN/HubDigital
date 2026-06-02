<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

final class MatrizEspeciesRequeridaException extends \DomainException
{
    public static function paraFinalizar(): self
    {
        return new self(
            'No se puede finalizar el envío de la solicitud sin una matriz de especímenes asociada'
        );
    }
}
