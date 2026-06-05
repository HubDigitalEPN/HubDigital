<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el servicio de IA (API externa) no está disponible o no responde.
 * Permite distinguir este fallo del resto de errores de extracción.
 */
final class ModeloIANoDisponibleException extends RuntimeException
{
    public static function porConexion(string $detalle): self
    {
        return new self("Modelo de IA no disponible: {$detalle}");
    }
}
