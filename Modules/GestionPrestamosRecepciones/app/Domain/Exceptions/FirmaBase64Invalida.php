<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

final class FirmaBase64Invalida extends DomainException
{
    public static function formatoInvalido(): self
    {
        return new self('La firma debe ser una imagen PNG en formato data URL (data:image/png;base64,...).');
    }

    public static function decodificacionFallida(): self
    {
        return new self('El contenido base64 de la firma no es válido y no puede ser decodificado.');
    }
}
