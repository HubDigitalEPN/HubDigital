<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Estados del ciclo de vida de un acta de préstamo
 * ({@see \Modules\GestionPrestamosRecepciones\Domain\Entities\ActaPrestamo}):
 * pendiente de envío → pendiente de firma → pendiente de validación → validada.
 */
enum EstadoActa: string
{
    case PendienteEnvio = 'pendiente_envio';
    case PendienteFirma = 'pendiente_firma';
    case PendienteValidacion = 'pendiente_validacion';
    case Validada = 'validada';

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
