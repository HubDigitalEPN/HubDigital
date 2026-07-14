<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Exceptions;

/** Lanzada cuando no se encuentra la recepción de lote de una solicitud. Usa {@see conSolicitud()} para construirla. */
final class RecepcionLoteNoEncontradaException extends ApplicationException
{
    public static function conSolicitud(string $solicitudId): self
    {
        return new self(sprintf('No se encontró una recepción de lote para la solicitud "%s"', $solicitudId));
    }
}
