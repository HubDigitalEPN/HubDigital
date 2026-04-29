<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Exceptions;

use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;

final class RfidNoAsignadoException extends \DomainException
{
    public function __construct(CajaId $cajaId)
    {
        parent::__construct(
            "La caja '{$cajaId}' no tiene ningún RFID asignado."
        );
    }
}
