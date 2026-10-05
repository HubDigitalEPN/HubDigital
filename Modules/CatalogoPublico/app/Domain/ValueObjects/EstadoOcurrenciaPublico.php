<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Traduce solo detecciones explícitas; un estado físico no demuestra detección ni ausencia. */
final class EstadoOcurrenciaPublico
{
    public static function deteccion(?string $original): ?string
    {
        return match (strtolower(trim($original ?? ''))) {
            'present', 'detected' => 'detected',
            'absent', 'notdetected' => 'notDetected',
            default => null,
        };
    }
}
