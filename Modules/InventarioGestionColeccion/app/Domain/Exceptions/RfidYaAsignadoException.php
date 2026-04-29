<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Exceptions;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoRfid;

final class RfidYaAsignadoException extends \DomainException
{
    public function __construct(CajaId $cajaId, CodigoRfid $rfidActual)
    {
        parent::__construct(
            "La caja '{$cajaId}' ya tiene el RFID '{$rfidActual}' asignado. Use reasignarRfid() para sobrescribirlo."
        );
    }
}
