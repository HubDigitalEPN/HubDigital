<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Tipo de trámite de ingreso de especímenes: depósito o donación.
 *
 * Determina reglas de negocio clave: las donaciones se validan técnicamente de
 * inmediato y no admiten corrección taxonómica, mientras que los depósitos pasan
 * por validación y pueden requerir documentación suplementaria.
 */
enum TipoTramite: string
{
    case Deposito = 'Depósito';
    case Donacion = 'Donación';

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
