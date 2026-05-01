<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;

final class RfidNoAsignadoException extends \DomainException
{
    public function __construct(CajaId $cajaId)
    {
        parent::__construct(
            "La caja '{$cajaId}' no tiene ningún RFID asignado."
        );
    }
}
