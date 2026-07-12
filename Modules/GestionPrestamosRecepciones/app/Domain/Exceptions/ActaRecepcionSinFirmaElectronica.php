<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Se lanza cuando el PDF que el curador sube como acta firmada no contiene una firma
 * electrónica válida (FirmaEC), según la verificación con pdfsig.
 */
final class ActaRecepcionSinFirmaElectronica extends DomainException
{
    public static function crear(): self
    {
        return new self('El documento subido no contiene una firma electrónica válida. Fírmalo con FirmaEC e inténtalo de nuevo.');
    }
}
