<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

final class DocumentacionInsuficiente extends \DomainException
{
    public static function paraEscalada(): self
    {
        return new self(
            'La solicitud debe estar marcada como sin documentación disponible para poder escalar a intervención curatorial.'
        );
    }
}
