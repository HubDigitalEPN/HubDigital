<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\ValueObjects;

/**
 * Clasificación del rechazo documental que decide el curador. Determina el estado
 * resultante de la solicitud: un rechazo subsanable la deja en corrección, mientras
 * que un rechazo definitivo la rechaza de forma permanente.
 */
enum TipoRechazoDocumental: string
{
    case Subsanable = 'Subsanable';
    case Definitivo = 'Definitivo';

    /** Estado al que transiciona la solicitud según el tipo de rechazo. */
    public function estadoResultante(): EstadoSolicitudDeposito
    {
        return match ($this) {
            self::Subsanable => EstadoSolicitudDeposito::RequiereCorreccion,
            self::Definitivo => EstadoSolicitudDeposito::RechazoPermanente,
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
