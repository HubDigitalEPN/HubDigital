<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

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
