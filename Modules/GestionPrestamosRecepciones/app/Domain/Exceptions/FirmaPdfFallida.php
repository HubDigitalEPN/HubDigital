<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use RuntimeException;

/**
 * Excepción lanzada cuando la firma criptográfica del PDF no se pudo completar
 * (el proceso de firma falló o no produjo un PDF firmado válido).
 */
final class FirmaPdfFallida extends RuntimeException
{
    public static function con(string $detalle): self
    {
        return new self("No se pudo firmar el PDF del acta: {$detalle}");
    }
}
