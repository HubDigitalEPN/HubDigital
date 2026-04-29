<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Exceptions;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoCaja;

final class CajaNoEnGabineteException extends \DomainException
{
    public function __construct(CajaId $cajaId, EstadoCaja $estadoActual)
    {
        parent::__construct(
            "La caja '{$cajaId}' no puede ser retirada porque su estado actual es '{$estadoActual->valor()}'. Se requiere estado 'en_gabinete'."
        );
    }
}
