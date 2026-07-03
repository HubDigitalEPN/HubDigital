<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Excepción de dominio lanzada al intentar generar un acta cuyo año no tiene
 * registrada la patente del laboratorio.
 */
final class PatenteAnualNoConfigurada extends DomainException
{
    public static function paraAnio(int $anio): self
    {
        return new self("No se ha registrado la patente del laboratorio para el año {$anio}. Regístrala en la configuración de préstamos antes de generar el acta.");
    }
}
