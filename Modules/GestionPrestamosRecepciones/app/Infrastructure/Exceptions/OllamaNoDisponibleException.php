<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando el servicio Ollama no está disponible o no responde.
 * Permite distinguir este fallo del resto de errores de extracción.
 */
final class OllamaNoDisponibleException extends RuntimeException
{
    public static function porConexion(string $detalle): self
    {
        return new self("Ollama no disponible: {$detalle}");
    }
}
