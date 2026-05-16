<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCaja;

final class CajaNoEnGabineteException extends \DomainException
{
    public function __construct(CajaId $cajaId, EstadoCaja $estadoActual)
    {
        parent::__construct(
            "La caja '{$cajaId}' no puede ser retirada porque su estado actual es '{$estadoActual->valor()}'. Se requiere estado 'en_gabinete'."
        );
    }
}
