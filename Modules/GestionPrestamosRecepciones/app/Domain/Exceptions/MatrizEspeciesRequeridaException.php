<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

/**
 * Excepción de dominio lanzada al intentar finalizar el envío de una solicitud sin
 * una matriz de especies asociada. Construir con {@see paraFinalizar()}.
 */
final class MatrizEspeciesRequeridaException extends \DomainException
{
    public static function paraFinalizar(): self
    {
        return new self(
            'No se puede finalizar el envío de la solicitud sin una matriz de especímenes asociada'
        );
    }
}
