<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Exceptions;

/** Lanzada cuando no se encuentra una SolicitudDeposito por su ID. Usa {@see conId()} para construirla. */
final class SolicitudNoEncontradaException extends ApplicationException
{
    public static function conId(string $solicitudId): self
    {
        return new self(sprintf('Solicitud de depósito "%s" no encontrada', $solicitudId));
    }
}
