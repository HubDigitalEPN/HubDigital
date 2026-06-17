<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

/**
 * Excepción de dominio lanzada cuando la matriz de especies no contiene campos DwC
 * críticos requeridos por la colección. Construir con {@see porCamposFaltantes()}.
 */
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
