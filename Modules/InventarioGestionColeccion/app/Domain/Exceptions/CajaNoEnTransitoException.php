<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Exceptions;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoCaja;

final class CajaNoEnTransitoException extends \DomainException
{
    public function __construct(CajaId $cajaId, EstadoCaja $estadoActual)
    {
        parent::__construct(
            "La caja '{$cajaId}' no puede ingresar a una ranura porque su estado actual es '{$estadoActual->valor()}'. Se requiere estado 'en_transito'."
        );
    }
}
